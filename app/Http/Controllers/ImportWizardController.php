<?php

namespace App\Http\Controllers;

use App\Actions\Imports\CreateImportRunFromUpload;
use App\Actions\Imports\PreviewImportRun;
use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRunStatus;
use App\Enums\ImportStrategy;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Services\Imports\EmployeeImportSchema;
use App\Services\Imports\EmployeeImportTemplate;
use App\Support\Imports\ImportField;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The Employee bulk-import wizard (KOL-94), one route per step per KOL-94.5's
 * locked contract. Upload (KOL-98), mapping review (KOL-99),
 * strategy/match-key (KOL-100), preview (KOL-101), and commit (KOL-102) exist
 * so far — the error-report download is a later ticket (KOL-103), adding its
 * own action without touching what's already here.
 */
class ImportWizardController extends Controller
{
    /**
     * The wizard shell's first step: template downloads plus the upload
     * form. No ImportRun exists yet, mirroring every other resource's
     * create/store split in this app.
     */
    public function create(): Response
    {
        return Inertia::render('imports/employee/create');
    }

    /**
     * `GET imports/employee/template/{format}` (KOL-94.8): a headers-only
     * file built from EmployeeImportSchema's field order, downloaded via the
     * same ReportWriter path as the employee master export.
     */
    public function template(string $format, EmployeeImportTemplate $template): HttpResponse
    {
        abort_unless(in_array($format, EmployeeImportTemplate::FORMATS, true), 404);

        return $template->download($format);
    }

    /**
     * `POST imports/employee` (KOL-94.5): validates the upload's real
     * format and row count, then transitions Pending -> MappingReview.
     */
    public function store(Request $request, CreateImportRunFromUpload $createImportRun): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $importRun = $createImportRun->handle($file);

        return to_route('imports.show', $importRun);
    }

    /**
     * `GET imports/{importRun}` (KOL-94.5): renders whatever step the run's
     * current status implies. Implicit route-model binding already scopes
     * to the current organization and the requesting user (ImportRun's
     * BelongsToOrganization and BelongsToUser global scopes, KOL-105), so a
     * cross-org id or another user's run in the same org 404s before this
     * method runs; the `Import:Employee` route middleware handles the 403
     * case.
     */
    public function show(ImportRun $importRun, EmployeeImportSchema $schema): Response
    {
        return Inertia::render('imports/employee/show', [
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
        ]);
    }

    /**
     * `PATCH imports/{importRun}/mapping` (KOL-94.5, KOL-99): persists the
     * reviewed ColumnMapping array. Strategy (KOL-100) isn't chosen yet at
     * this step, so every one of EmployeeImportSchema's CreateOnly-required
     * fields must always be mapped, regardless of which strategy gets picked
     * later. Allowed while MappingReview or PreviewReady; demoting a
     * PreviewReady run back to MappingReview on resubmit is KOL-101's job —
     * preview doesn't exist yet, so this never actually sees PreviewReady.
     */
    public function updateMapping(Request $request, ImportRun $importRun, EmployeeImportSchema $schema): RedirectResponse
    {
        abort_unless(
            in_array($importRun->status, [ImportRunStatus::MappingReview, ImportRunStatus::PreviewReady], true),
            409,
        );

        $fieldsByName = collect($schema->fields())->keyBy(fn (ImportField $field): string => $field->name);

        $validated = $request->validate([
            'mapping' => ['required', 'array', 'size:'.count($importRun->column_mapping ?? []), $this->mappingValidator($importRun, $fieldsByName)],
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
     * `PATCH imports/{importRun}/strategy` (KOL-94.5, KOL-100): persists
     * which strategy the run is allowed to take and, when that strategy
     * matches existing rows at all ({@see ImportStrategy::allowsMatching()}),
     * which field identifies them. Guarded exactly like {@see updateMapping}.
     * A valid match key submitted alongside CreateOnly is silently dropped
     * rather than persisted — CreateOnly never looks one up, so keeping it
     * would only leave stale state behind if the user switches strategy
     * back; an unrecognized match key is still rejected regardless of
     * strategy, same as any other invalid input.
     */
    public function updateStrategy(Request $request, ImportRun $importRun, EmployeeImportSchema $schema): RedirectResponse
    {
        abort_unless(
            in_array($importRun->status, [ImportRunStatus::MappingReview, ImportRunStatus::PreviewReady], true),
            409,
        );

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
     * `POST imports/{importRun}/preview` (KOL-94.5, KOL-101): evaluates
     * every uploaded data row through EvaluateImportRow synchronously and
     * persists the aggregate preview_counts; MappingReview -> PreviewReady.
     * Reachable only from MappingReview — reaching PreviewReady again after
     * a demotion (AC #3) requires rerunning this endpoint deliberately, not
     * an implicit re-preview. The mapping/strategy prerequisites are
     * re-checked here even though the client already gates on them, so a
     * request that bypasses that gate still fails cleanly instead of
     * evaluating incomplete data.
     */
    public function preview(ImportRun $importRun, EmployeeImportSchema $schema, PreviewImportRun $previewImportRun): RedirectResponse
    {
        abort_unless($importRun->status === ImportRunStatus::MappingReview, 409);

        $this->assertReadyForPreview($importRun, $schema);

        $previewImportRun->handle($importRun, $schema);

        return back();
    }

    /**
     * `POST imports/{importRun}/commit` (KOL-94.5, KOL-102): the run must
     * already have a preview computed against the exact mapping/strategy it
     * commits with, so this is only reachable from PreviewReady — editing
     * mapping/strategy again after this point demotes the run back to
     * MappingReview (see {@see demotionFrom}) before it can reach here.
     * Flips to Processing itself, before dispatch (AC #1). The transition is
     * a single conditional UPDATE rather than a fetch-then-write, so two
     * near-simultaneous requests can't both observe PreviewReady and both
     * dispatch ProcessImportRun — only the request whose UPDATE actually
     * changes a row gets to dispatch; the other 409s.
     */
    public function commit(ImportRun $importRun): RedirectResponse
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

        return ['status' => ImportRunStatus::MappingReview, 'preview_counts' => null];
    }

    private function assertReadyForPreview(ImportRun $importRun, EmployeeImportSchema $schema): void
    {
        $fieldsByName = collect($schema->fields())->keyBy(fn (ImportField $field): string => $field->name);

        $mappedTargets = collect($importRun->column_mapping ?? [])
            ->where('status', ColumnMappingStatus::Mapped->value)
            ->pluck('targetField');

        $missingRequired = $this->missingRequiredFields($fieldsByName, $mappedTargets);

        if ($missingRequired->isNotEmpty()) {
            throw ValidationException::withMessages([
                'preview' => __('ui.employees.import.errors.required_field_unmapped', [
                    'fields' => $missingRequired->map(fn (string $name): string => $fieldsByName[$name]->label)->implode(', '),
                ]),
            ]);
        }

        if ($importRun->strategy === null) {
            throw ValidationException::withMessages([
                'preview' => __('ui.employees.import.errors.strategy_required'),
            ]);
        }

        if ($importRun->strategy->allowsMatching() && $importRun->match_key === null) {
            throw ValidationException::withMessages([
                'preview' => __('ui.employees.import.errors.match_key_required'),
            ]);
        }
    }

    /**
     * @param  Collection<string, ImportField>  $fieldsByName
     */
    private function mappingValidator(ImportRun $importRun, Collection $fieldsByName): Closure
    {
        /** @var Collection<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}> $originalByIndex */
        $originalByIndex = collect($importRun->column_mapping ?? [])->keyBy('sourceColumnIndex');

        return function (string $attribute, mixed $value, Closure $fail) use ($originalByIndex, $fieldsByName): void {
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
                $fail(__('ui.employees.import.errors.invalid_mapping_shape'));

                return;
            }

            foreach ($rows as $row) {
                $original = $originalByIndex->get((int) $row['sourceColumnIndex']);

                if ($original['sourceHeaderLabel'] !== $row['sourceHeaderLabel']) {
                    $fail(__('ui.employees.import.errors.invalid_mapping_shape'));

                    return;
                }

                $isMapped = $row['status'] === ColumnMappingStatus::Mapped->value;

                if ($isMapped !== ($row['targetField'] !== null)) {
                    $fail(__('ui.employees.import.errors.invalid_mapping_shape'));

                    return;
                }
            }

            $mappedTargets = $rows
                ->where('status', ColumnMappingStatus::Mapped->value)
                ->pluck('targetField');

            if ($mappedTargets->diff($fieldsByName->keys())->isNotEmpty()) {
                $fail(__('ui.employees.import.errors.unknown_target_field'));

                return;
            }

            if ($mappedTargets->duplicates()->isNotEmpty()) {
                $fail(__('ui.employees.import.errors.duplicate_target_field'));

                return;
            }

            $missingRequired = $this->missingRequiredFields($fieldsByName, $mappedTargets);

            if ($missingRequired->isNotEmpty()) {
                $fail(__('ui.employees.import.errors.required_field_unmapped', [
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
