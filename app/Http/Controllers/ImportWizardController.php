<?php

namespace App\Http\Controllers;

use App\Actions\Imports\CreateImportRunFromUpload;
use App\Actions\Imports\DownloadImportErrorReport;
use App\Actions\Imports\PreviewImportRun;
use App\Concerns\ResolvesTablePerPage;
use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRunStatus;
use App\Enums\ImportStrategy;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Models\ImportRunIssue;
use App\Services\Imports\ImportResourceRegistry;
use App\Services\Imports\ImportSchema;
use App\Support\Imports\ImportField;
use App\Support\Imports\ImportFieldLabels;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The bulk-import wizard (KOL-94, generalized beyond Employee by KOL-107),
 * one route per step per KOL-94.5's locked contract: upload (KOL-98),
 * mapping review (KOL-99), strategy/match-key (KOL-100), preview (KOL-101),
 * commit (KOL-102), and the error-report download (KOL-103). Every action
 * resolves its ImportSchema/permission/template from the `{resourceType}`
 * route segment via {@see ImportResourceRegistry} instead of type-hinting a
 * concrete resource's classes, so a future resource (KOL-108's Shift
 * Assignments) needs only a registry entry — no changes here.
 */
class ImportWizardController extends Controller
{
    use ResolvesTablePerPage;

    /**
     * The wizard shell's first step: template downloads plus the upload
     * form. No ImportRun exists yet, mirroring every other resource's
     * create/store split in this app.
     */
    public function create(string $resourceType): Response
    {
        return Inertia::render("imports/{$resourceType}/create", [
            'resourceType' => $resourceType,
        ]);
    }

    /**
     * `GET imports/{resourceType}/template/{format}` (KOL-94.8): a
     * headers-only file built from the resource's schema field order,
     * downloaded via the same ReportWriter path as the employee master
     * export.
     */
    public function template(string $resourceType, string $format): HttpResponse
    {
        $template = ImportResourceRegistry::findOrFail($resourceType)->template();

        abort_unless(in_array($format, $template->formats(), true), 404);

        return $template->download($format);
    }

    /**
     * `POST imports/{resourceType}` (KOL-94.5): validates the upload's real
     * format and row count, then transitions Pending -> MappingReview.
     */
    public function store(string $resourceType, Request $request, CreateImportRunFromUpload $createImportRun): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $importRun = $createImportRun->handle($resourceType, $file);

        return to_route('imports.show', [$resourceType, $importRun]);
    }

    /**
     * `GET imports/{resourceType}/{importRun}` (KOL-94.5): renders whatever
     * step the run's current status implies. Implicit route-model binding
     * already scopes to the current organization and the requesting user
     * (ImportRun's BelongsToOrganization and BelongsToUser global scopes,
     * KOL-105) and rejects a resourceType/importRun mismatch (KOL-107's
     * `Route::bind('importRun', ...)` in routes/web.php), so a cross-org
     * id, another user's run, or a run belonging to a different resource
     * type all 404 before this method runs; the `import.permission` route
     * middleware handles the 403 case.
     */
    public function show(Request $request, string $resourceType, ImportRun $importRun): Response
    {
        $schema = ImportResourceRegistry::findOrFail($resourceType)->schema();

        return Inertia::render("imports/{$resourceType}/show", [
            'resourceType' => $resourceType,
            'importRun' => [
                'id' => $importRun->id,
                'status' => $importRun->status->value,
                'original_filename' => $importRun->original_filename,
                'column_mapping' => $importRun->column_mapping ?? [],
                'strategy' => $importRun->strategy?->value,
                'match_key' => $importRun->match_key,
                'preview_counts' => $importRun->preview_counts,
                'created_count' => $importRun->created_count,
                'updated_count' => $importRun->updated_count,
                'skipped_count' => $importRun->skipped_count,
                'errored_count' => $importRun->errored_count,
            ],
            'schemaFields' => collect($schema->fields())
                ->map(fn (ImportField $field): array => [
                    'name' => $field->name,
                    'label' => $field->label,
                    'requiredForCreateOnly' => $field->requiredForCreateOnly,
                    'isMatchKeyEligible' => $field->isMatchKeyEligible,
                ])
                ->values(),
            'issues' => $this->issuesTable($request, $importRun, $schema),
        ]);
    }

    /**
     * The preview step's per-row issue table (KOL-111): only queried once a
     * preview actually found something wrong, mirroring the KOL-101 AC #2
     * gate ("shown whenever preview_counts.error > 0 or .warning > 0"). Read
     * through `$importRun->issues()` (never `ImportRunIssue::query()`
     * directly) so it inherits ImportRun's org+user scope (KOL-105) for
     * free.
     */
    private function issuesTable(Request $request, ImportRun $importRun, ImportSchema $schema): mixed
    {
        $counts = $importRun->preview_counts;

        if ($counts === null || ($counts['error'] === 0 && $counts['warning'] === 0)) {
            return null;
        }

        $labels = ImportFieldLabels::build($schema);

        return $importRun->issues()
            ->paginate($this->resolveTablePerPage($request))
            ->withQueryString()
            ->through(fn (ImportRunIssue $issue): array => [
                'id' => $issue->id,
                'row' => $issue->row_number,
                'column' => $issue->field !== null ? ($labels[$issue->field] ?? $issue->field) : '',
                'severity' => $issue->severity->label(),
                'message' => $issue->message,
            ]);
    }

    /**
     * `PATCH imports/{resourceType}/{importRun}/mapping` (KOL-94.5, KOL-99):
     * persists the reviewed ColumnMapping array. Strategy (KOL-100) isn't
     * chosen yet at this step, so every one of the schema's
     * CreateOnly-required fields must always be mapped, regardless of which
     * strategy gets picked later. Allowed while MappingReview or
     * PreviewReady; demoting a PreviewReady run back to MappingReview on
     * resubmit is KOL-101's job — preview doesn't exist yet, so this never
     * actually sees PreviewReady.
     */
    public function updateMapping(Request $request, string $resourceType, ImportRun $importRun): RedirectResponse
    {
        abort_unless(
            in_array($importRun->status, [ImportRunStatus::MappingReview, ImportRunStatus::PreviewReady], true),
            409,
        );

        $schema = ImportResourceRegistry::findOrFail($resourceType)->schema();
        $fieldsByName = collect($schema->fields())->keyBy(fn (ImportField $field): string => $field->name);

        $validated = $request->validate([
            'mapping' => ['required', 'array', 'size:'.count($importRun->column_mapping ?? []), $this->mappingValidator($importRun, $fieldsByName, $resourceType)],
            'mapping.*.sourceColumnIndex' => ['required', 'integer', 'min:0'],
            'mapping.*.sourceHeaderLabel' => ['nullable', 'string'],
            'mapping.*.targetField' => ['nullable', 'string'],
            'mapping.*.status' => ['required', Rule::enum(ColumnMappingStatus::class)],
        ]);

        $importRun->update([
            'column_mapping' => $validated['mapping'],
            ...$this->demotionFrom($importRun),
        ]);

        return back();
    }

    /**
     * `PATCH imports/{resourceType}/{importRun}/strategy` (KOL-94.5,
     * KOL-100): persists which strategy the run is allowed to take and,
     * when that strategy matches existing rows at all
     * ({@see ImportStrategy::allowsMatching()}), which field identifies
     * them. Guarded exactly like {@see updateMapping}. A valid match key
     * submitted alongside CreateOnly is silently dropped rather than
     * persisted — CreateOnly never looks one up, so keeping it would only
     * leave stale state behind if the user switches strategy back; an
     * unrecognized match key is still rejected regardless of strategy, same
     * as any other invalid input.
     */
    public function updateStrategy(Request $request, string $resourceType, ImportRun $importRun): RedirectResponse
    {
        abort_unless(
            in_array($importRun->status, [ImportRunStatus::MappingReview, ImportRunStatus::PreviewReady], true),
            409,
        );

        $schema = ImportResourceRegistry::findOrFail($resourceType)->schema();

        $matchKeyEligible = collect($schema->fields())
            ->filter(fn (ImportField $field): bool => $field->isMatchKeyEligible)
            ->map(fn (ImportField $field): string => $field->name);

        $needsMatchKey = ImportStrategy::tryFrom((string) $request->input('strategy'))?->allowsMatching() ?? false;

        $validated = $request->validate([
            'strategy' => ['required', Rule::enum(ImportStrategy::class)],
            'match_key' => [
                Rule::requiredIf($needsMatchKey),
                'nullable', 'string', Rule::in($matchKeyEligible),
            ],
        ]);

        $strategy = ImportStrategy::from($validated['strategy']);

        $importRun->update([
            'strategy' => $strategy,
            'match_key' => $strategy->allowsMatching() ? $validated['match_key'] : null,
            ...$this->demotionFrom($importRun),
        ]);

        return back();
    }

    /**
     * `POST imports/{resourceType}/{importRun}/preview` (KOL-94.5, KOL-101):
     * evaluates every uploaded data row through EvaluateImportRow
     * synchronously and persists the aggregate preview_counts; MappingReview
     * -> PreviewReady. Reachable only from MappingReview — reaching
     * PreviewReady again after a demotion (AC #3) requires rerunning this
     * endpoint deliberately, not an implicit re-preview. The
     * mapping/strategy prerequisites are re-checked here even though the
     * client already gates on them, so a request that bypasses that gate
     * still fails cleanly instead of evaluating incomplete data.
     */
    public function preview(string $resourceType, ImportRun $importRun, PreviewImportRun $previewImportRun): RedirectResponse
    {
        abort_unless($importRun->status === ImportRunStatus::MappingReview, 409);

        $schema = ImportResourceRegistry::findOrFail($resourceType)->schema();

        $this->assertReadyForPreview($importRun, $schema, $resourceType);

        $previewImportRun->handle($importRun, $schema);

        return back();
    }

    /**
     * `POST imports/{resourceType}/{importRun}/commit` (KOL-94.5, KOL-102):
     * the run must already have a preview computed against the exact
     * mapping/strategy it commits with, so this is only reachable from
     * PreviewReady — editing mapping/strategy again after this point
     * demotes the run back to MappingReview (see {@see demotionFrom})
     * before it can reach here. Flips to Processing itself, before dispatch
     * (AC #1). The transition is a single conditional UPDATE rather than a
     * fetch-then-write, so two near-simultaneous requests can't both
     * observe PreviewReady and both dispatch ProcessImportRun — only the
     * request whose UPDATE actually changes a row gets to dispatch; the
     * other 409s.
     */
    public function commit(string $resourceType, ImportRun $importRun): RedirectResponse
    {
        $transitioned = ImportRun::query()
            ->whereKey($importRun->id)
            ->where('status', ImportRunStatus::PreviewReady)
            ->update(['status' => ImportRunStatus::Processing]);

        abort_unless($transitioned === 1, 409);

        ProcessImportRun::dispatch($importRun->id);

        return back();
    }

    /**
     * `GET imports/{resourceType}/{importRun}/error-report` (KOL-94.5,
     * KOL-94.8, KOL-103): streams the CSV ProcessImportRun wrote during its
     * commit pass. Same org+user route-model-binding scope as every other
     * wizard route; {@see DownloadImportErrorReport} itself refuses a run
     * with nothing to report.
     */
    public function errorReport(string $resourceType, ImportRun $importRun, DownloadImportErrorReport $download): HttpResponse
    {
        return $download->handle($importRun);
    }

    /**
     * `DELETE imports/{resourceType}/{importRun}` (KOL-109): lets the
     * owning user abandon an in-progress run instead of waiting for
     * PruneAbandonedImportRuns (KOL-104) to expire it. Only reachable while
     * nothing has actually started committing yet — Pending is accepted for
     * consistency even though it's never visible in the wizard UI
     * (CreateImportRunFromUpload transitions it synchronously or deletes it
     * on failure). Reuses the exact disk_path cleanup
     * PruneAbandonedImportRuns already does; deleting the row cascades to
     * its ImportRunIssue rows (KOL-111) at the database level.
     */
    public function destroy(string $resourceType, ImportRun $importRun): RedirectResponse
    {
        $definition = ImportResourceRegistry::findOrFail($resourceType);

        abort_unless(
            in_array($importRun->status, [
                ImportRunStatus::Pending,
                ImportRunStatus::MappingReview,
                ImportRunStatus::PreviewReady,
            ], true),
            409,
        );

        if ($importRun->disk_path !== null) {
            Storage::disk('local')->delete($importRun->disk_path);
        }

        $importRun->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("ui.{$resourceType}.import.flash.cancelled")]);

        return to_route($definition->indexRoute);
    }

    /**
     * A resubmitted mapping/strategy while the run is already PreviewReady
     * invalidates that preview (KOL-101 AC #3): demote back to
     * MappingReview and clear preview_counts so the commit step re-locks
     * until preview reruns against the new mapping/strategy.
     *
     * @return array{status?: ImportRunStatus, preview_counts?: null}
     */
    private function demotionFrom(ImportRun $importRun): array
    {
        if ($importRun->status !== ImportRunStatus::PreviewReady) {
            return [];
        }

        $importRun->issues()->delete();

        return ['status' => ImportRunStatus::MappingReview, 'preview_counts' => null];
    }

    private function assertReadyForPreview(ImportRun $importRun, ImportSchema $schema, string $resourceType): void
    {
        $fieldsByName = collect($schema->fields())->keyBy(fn (ImportField $field): string => $field->name);

        $mappedTargets = collect($importRun->column_mapping ?? [])
            ->where('status', ColumnMappingStatus::Mapped->value)
            ->pluck('targetField');

        $missingRequired = $this->missingRequiredFields($fieldsByName, $mappedTargets);

        if ($missingRequired->isNotEmpty()) {
            throw ValidationException::withMessages([
                'preview' => __("ui.{$resourceType}.import.errors.required_field_unmapped", [
                    'fields' => $missingRequired->map(fn (string $name): string => $fieldsByName[$name]->label)->implode(', '),
                ]),
            ]);
        }

        if ($importRun->strategy === null) {
            throw ValidationException::withMessages([
                'preview' => __("ui.{$resourceType}.import.errors.strategy_required"),
            ]);
        }

        if ($importRun->strategy->allowsMatching() && $importRun->match_key === null) {
            throw ValidationException::withMessages([
                'preview' => __("ui.{$resourceType}.import.errors.match_key_required"),
            ]);
        }
    }

    /**
     * @param  Collection<string, ImportField>  $fieldsByName
     */
    private function mappingValidator(ImportRun $importRun, Collection $fieldsByName, string $resourceType): Closure
    {
        /** @var Collection<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}> $originalByIndex */
        $originalByIndex = collect($importRun->column_mapping ?? [])->keyBy('sourceColumnIndex');

        return function (string $attribute, mixed $value, Closure $fail) use ($originalByIndex, $fieldsByName, $resourceType): void {
            /** @var array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}> $value */
            $rows = collect($value);

            // sourceColumnIndex/sourceHeaderLabel describe the uploaded file
            // itself — only targetField/status are the user's to edit. A
            // duplicate, missing, or relabeled index would otherwise let a
            // crafted request silently corrupt what EvaluateImportRow later
            // reads each column as (KOL-101+).
            $submittedIndices = $rows->pluck('sourceColumnIndex')->map(fn (mixed $i): int => (int) $i);

            if ($submittedIndices->unique()->count() !== $submittedIndices->count()
                || $submittedIndices->diff($originalByIndex->keys())->isNotEmpty()) {
                $fail(__("ui.{$resourceType}.import.errors.invalid_mapping_shape"));

                return;
            }

            foreach ($rows as $row) {
                $original = $originalByIndex->get((int) $row['sourceColumnIndex']);

                if ($original['sourceHeaderLabel'] !== $row['sourceHeaderLabel']) {
                    $fail(__("ui.{$resourceType}.import.errors.invalid_mapping_shape"));

                    return;
                }

                $isMapped = $row['status'] === ColumnMappingStatus::Mapped->value;

                if ($isMapped !== ($row['targetField'] !== null)) {
                    $fail(__("ui.{$resourceType}.import.errors.invalid_mapping_shape"));

                    return;
                }
            }

            $mappedTargets = $rows
                ->where('status', ColumnMappingStatus::Mapped->value)
                ->pluck('targetField');

            if ($mappedTargets->diff($fieldsByName->keys())->isNotEmpty()) {
                $fail(__("ui.{$resourceType}.import.errors.unknown_target_field"));

                return;
            }

            if ($mappedTargets->duplicates()->isNotEmpty()) {
                $fail(__("ui.{$resourceType}.import.errors.duplicate_target_field"));

                return;
            }

            $missingRequired = $this->missingRequiredFields($fieldsByName, $mappedTargets);

            if ($missingRequired->isNotEmpty()) {
                $fail(__("ui.{$resourceType}.import.errors.required_field_unmapped", [
                    'fields' => $missingRequired->map(fn (string $name): string => $fieldsByName[$name]->label)->implode(', '),
                ]));
            }
        };
    }

    /**
     * The set of CreateOnly-required field names not present among
     * `$mappedTargets` — shared by {@see mappingValidator}'s submitted-row
     * check and {@see assertReadyForPreview}'s stored-mapping check so the
     * "required" definition can't drift between the two.
     *
     * @param  Collection<string, ImportField>  $fieldsByName
     * @param  Collection<int, string>  $mappedTargets
     * @return Collection<int, string>
     */
    private function missingRequiredFields(Collection $fieldsByName, Collection $mappedTargets): Collection
    {
        return $fieldsByName
            ->filter(fn (ImportField $field): bool => $field->requiredForCreateOnly)
            ->keys()
            ->diff($mappedTargets)
            ->values();
    }
}
