<?php

namespace App\Jobs;

use App\Actions\Imports\BuildColumnMappings;
use App\Actions\Imports\EvaluateImportRow;
use App\Actions\Imports\ImportErrorReportWriter;
use App\Actions\Imports\ReadImportFileRows;
use App\Enums\ImportIssueSeverity;
use App\Enums\ImportRowStatus;
use App\Enums\ImportRunStatus;
use App\Models\Company;
use App\Models\ImportRun;
use App\Models\User;
use App\Notifications\ImportRunCompleted;
use App\Notifications\ImportRunFailed;
use App\Services\Imports\EmployeeImportSchema;
use App\Support\CurrentOrganization;
use App\Support\Imports\ColumnMapping;
use App\Support\Imports\ImportIssue;
use App\Support\Imports\ImportRow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * The Employee bulk-import wizard's commit pass (KOL-94.4, KOL-102): every
 * data row runs back through {@see EvaluateImportRow} — same evaluation as
 * the preview step (KOL-101), against the same mapping/strategy/match key —
 * and a Ready row is actually written. Chunked into its own
 * DB::transaction() per `config('imports.commit_chunk_size')` rows, with
 * `committed_through` and the running counts persisted at the end of each
 * successful chunk so a retry ({@see self::$tries}) resumes rather than
 * re-applying already-committed rows.
 */
class ProcessImportRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $importRunId) {}

    public function handle(
        EmployeeImportSchema $schema,
        EvaluateImportRow $evaluateImportRow,
        ReadImportFileRows $readImportFileRows,
        BuildColumnMappings $buildColumnMappings,
        ImportErrorReportWriter $errorReportWriter,
    ): void {
        $importRun = ImportRun::findOrFail($this->importRunId);

        // A queued job has no HTTP session or authenticated user for the
        // schema's reference/match-key/uniqueness lookups to resolve a
        // tenant from — force it to the run's own organization for the
        // duration, the one value that's actually reliable here.
        CurrentOrganization::runAs(
            $importRun->organization_id,
            fn () => $this->commit($importRun, $schema, $evaluateImportRow, $readImportFileRows, $buildColumnMappings, $errorReportWriter),
        );
    }

    private function commit(
        ImportRun $importRun,
        EmployeeImportSchema $schema,
        EvaluateImportRow $evaluateImportRow,
        ReadImportFileRows $readImportFileRows,
        BuildColumnMappings $buildColumnMappings,
        ImportErrorReportWriter $errorReportWriter,
    ): void {
        $columnMappings = $buildColumnMappings->handle($importRun);
        $dataRows = $readImportFileRows->handle($importRun);

        $committedThrough = $importRun->committed_through ?? 0;

        $counts = [
            'created' => $importRun->created_count,
            'updated' => $importRun->updated_count,
            'skipped' => $importRun->skipped_count,
            'errored' => $importRun->errored_count,
        ];

        $errorReportWriter->open($importRun, $schema);

        try {
            // A retry never re-runs an already-committed row through
            // EvaluateImportRow below (that's the whole point of
            // committed_through), so its issues have to be re-derived here
            // to keep the file's "regenerated from scratch every attempt"
            // guarantee (AC #1) — evaluation is pure and side-effect-free,
            // unlike applyRow's writes, so redoing it is safe.
            foreach (array_slice($dataRows, 0, $committedThrough, true) as $index => $rawRow) {
                $this->evaluateAndRecord($schema, $evaluateImportRow, $columnMappings, $rawRow, $index + 2, $importRun, $errorReportWriter);
            }

            $remainingRows = array_slice($dataRows, $committedThrough, null, true);
            $chunkSize = max(1, (int) config('imports.commit_chunk_size', 200));

            foreach (array_chunk($remainingRows, $chunkSize, true) as $chunk) {
                DB::transaction(function () use ($chunk, $importRun, $schema, $evaluateImportRow, $columnMappings, &$counts, &$committedThrough, $errorReportWriter): void {
                    foreach ($chunk as $index => $rawRow) {
                        $result = $this->evaluateAndRecord($schema, $evaluateImportRow, $columnMappings, $rawRow, $index + 2, $importRun, $errorReportWriter);

                        $this->applyRow($schema, $result, $importRun, $counts, $errorReportWriter);
                    }

                    $committedThrough += count($chunk);

                    $importRun->update([
                        'committed_through' => $committedThrough,
                        'created_count' => $counts['created'],
                        'updated_count' => $counts['updated'],
                        'skipped_count' => $counts['skipped'],
                        'errored_count' => $counts['errored'],
                    ]);
                });
            }
        } finally {
            $errorReportWriter->close();
        }

        $importRun->update(['status' => ImportRunStatus::Completed]);

        $importRun->user->notify(new ImportRunCompleted($importRun));
    }

    /**
     * @param  list<ColumnMapping>  $columnMappings
     * @param  list<mixed>  $rawRow
     */
    private function evaluateAndRecord(
        EmployeeImportSchema $schema,
        EvaluateImportRow $evaluateImportRow,
        array $columnMappings,
        array $rawRow,
        int $rowNumber,
        ImportRun $importRun,
        ImportErrorReportWriter $errorReportWriter,
    ): ImportRow {
        $result = $evaluateImportRow->handle(
            $schema,
            $columnMappings,
            $rawRow,
            $rowNumber,
            $importRun->strategy,
            $importRun->match_key,
        );

        $errorReportWriter->write($rowNumber, $result->issues);

        return $result;
    }

    /**
     * A DB constraint the row's own INSERT/UPDATE hits (e.g. a unique-key
     * race against a row committed outside this run) counts that single row
     * as Error without aborting the chunk or the job (AC #4) — MySQL, unlike
     * Postgres, does not poison the rest of an open transaction over one
     * failed statement, so the loop can keep using the same transaction.
     * Only {@see self::save()}'s own write is guarded: the role assignment
     * that follows a create is a system-level step, not row data, so a
     * failure there is deliberately left to propagate as a real job failure
     * (AC #5) rather than being miscounted as this row's Error while the
     * User record it just wrote stays committed.
     *
     * @param  array{created: int, updated: int, skipped: int, errored: int}  $counts
     */
    private function applyRow(EmployeeImportSchema $schema, ImportRow $result, ImportRun $importRun, array &$counts, ImportErrorReportWriter $errorReportWriter): void
    {
        if ($result->status === ImportRowStatus::Error) {
            $counts['errored']++;

            return;
        }

        if ($result->status !== ImportRowStatus::Ready) {
            // Skipped (UpdateOnly with no match) and the never-emitted
            // Warning both mean "don't write this row".
            $counts['skipped']++;

            return;
        }

        try {
            $model = $this->save($schema, $result, $importRun);
        } catch (QueryException) {
            $counts['errored']++;

            // EvaluateImportRow's own issues (written above, in
            // evaluateAndRecord) are empty for a Ready row — this is the
            // only place a save-time constraint race becomes visible, so it
            // gets its own report line rather than an errored_count with
            // nothing on the CSV to explain it.
            $errorReportWriter->write($result->rowNumber, [
                new ImportIssue(null, 'A database constraint was violated while saving this row (e.g. a duplicate value already in use).', ImportIssueSeverity::Error),
            ]);

            return;
        }

        if ($model->wasRecentlyCreated) {
            $model->assignRole('employee');
            $counts['created']++;
        } else {
            $counts['updated']++;
        }
    }

    private function save(EmployeeImportSchema $schema, ImportRow $result, ImportRun $importRun): User
    {
        $targetModelClass = $schema->targetModel();

        /** @var User|null $existingMatch */
        $existingMatch = $result->matchedModelId !== null
            ? $targetModelClass::query()->find($result->matchedModelId)
            : null;

        $model = $existingMatch ?? $this->newEmployee($importRun);

        $model->fill($result->resolvedData);
        // `name` isn't one of EmployeeImportSchema's fields (KOL-94.2) — it's
        // derived, same as the manual create/edit form does
        // (EmployeeController::prepareForStorage). Recomputing it from the
        // model's own post-fill first_name/last_name works for both create
        // (both required) and update (an omitted blank cell leaves the
        // existing value in place, per the framework's blank-means-no-change
        // policy), so no separate "did this row touch the name" branch is
        // needed.
        $model->name = trim("{$model->first_name} {$model->last_name}");

        $model->save();

        return $model;
    }

    /**
     * A newly imported employee needs several attributes the schema
     * deliberately never collects (KOL-94.2 — `company` is excluded because
     * it's "auto-assigned per organization", `password` and `avatar` are
     * excluded entirely): the tenant/company stamp (User carries no
     * BelongsToOrganization scope to do this automatically, unlike every
     * other org-scoped model — EmployeeController::store() stamps it the
     * same way), and a random password, since nothing in an imported file
     * ever supplies one — the employee sets their own via "forgot password".
     */
    private function newEmployee(ImportRun $importRun): User
    {
        return new User([
            'organization_id' => $importRun->organization_id,
            'company_id' => Company::query()->where('organization_id', $importRun->organization_id)->value('id'),
            'password' => Hash::make(Str::random(40)),
        ]);
    }

    /**
     * An exception outside per-row handling (unreadable/corrupt upload,
     * reader failure, unrecoverable DB/OOM) once retries are exhausted (AC
     * #5) — every row-level failure (validation, unresolved reference,
     * unique-constraint race) is already caught inside {@see self::applyRow()}
     * and never reaches here.
     */
    public function failed(?Throwable $exception): void
    {
        $importRun = ImportRun::find($this->importRunId);

        if ($importRun === null) {
            return;
        }

        $importRun->update(['status' => ImportRunStatus::Failed]);

        $importRun->user->notify(new ImportRunFailed($importRun));
    }

    /**
     * Tags so a stuck import is legible in Horizon/Telescope without opening
     * the payload.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['import-run:'.$this->importRunId];
    }
}
