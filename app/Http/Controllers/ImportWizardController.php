<?php

namespace App\Http\Controllers;

use App\Actions\Imports\CreateImportRunFromUpload;
use App\Enums\ColumnMappingStatus;
use App\Enums\ImportRunStatus;
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
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The Employee bulk-import wizard (KOL-94), one route per step per KOL-94.5's
 * locked contract. Upload (KOL-98) and mapping review (KOL-99) exist so
 * far — strategy/match-key, preview, commit and the error-report download
 * are later tickets (KOL-100..103), each adding their own action to
 * {@see show}'s status switch without touching what's already here.
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
     * to the current organization (ImportRun's BelongsToOrganization global
     * scope), so a cross-org id 404s before this method runs; the
     * `Import:Employee` route middleware handles the 403 case.
     */
    public function show(ImportRun $importRun, EmployeeImportSchema $schema): Response
    {
        return Inertia::render('imports/employee/show', [
            'importRun' => [
                'id' => $importRun->id,
                'status' => $importRun->status->value,
                'original_filename' => $importRun->original_filename,
                'column_mapping' => $importRun->column_mapping ?? [],
            ],
            'schemaFields' => collect($schema->fields())
                ->map(fn (ImportField $field): array => [
                    'name' => $field->name,
                    'label' => $field->label,
                    'requiredForCreateOnly' => $field->requiredForCreateOnly,
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

        $importRun->update(['column_mapping' => $validated['mapping']]);

        return back();
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

            $missingRequired = $fieldsByName
                ->filter(fn (ImportField $field): bool => $field->requiredForCreateOnly)
                ->keys()
                ->diff($mappedTargets);

            if ($missingRequired->isNotEmpty()) {
                $fail(__('ui.employees.import.errors.required_field_unmapped', [
                    'fields' => $missingRequired->map(fn (string $name): string => $fieldsByName[$name]->label)->implode(', '),
                ]));
            }
        };
    }
}
