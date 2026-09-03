<?php

namespace App\Http\Controllers;

use App\Actions\Imports\CreateImportRunFromUpload;
use App\Models\ImportRun;
use App\Services\Imports\EmployeeImportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The Employee bulk-import wizard (KOL-94), one route per step per KOL-94.5's
 * locked contract. Only the upload step exists so far (KOL-98) — mapping
 * review, strategy/match-key, preview, commit and the error-report download
 * are later tickets (KOL-99..103), each adding their own action to
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
    public function show(ImportRun $importRun): Response
    {
        return Inertia::render('imports/employee/show', [
            'importRun' => [
                'id' => $importRun->id,
                'status' => $importRun->status->value,
                'original_filename' => $importRun->original_filename,
                'column_count' => count($importRun->column_mapping ?? []),
            ],
        ]);
    }
}
