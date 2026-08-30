<?php

namespace App\Services\Reports;

use App\Http\Controllers\EmployeeController;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns "Maestro de Trabajadores" (RF-1, KOL-23) — the bulk employee master
 * dump an accountant loads first when setting up a client, before any hours
 * matter — into a downloadable Excel or CSV file via the shared
 * {@see ReportWriter} (KOL-15).
 *
 * Unlike the other RF-1 reports this is a snapshot, not a period figure: the
 * caller passes in whatever employees {@see EmployeeController::filteredEmployeesQuery()}
 * already resolved, and every row here is read straight off the model —
 * nothing is calculated.
 *
 * Excel and CSV only, per the PRD's report table — no PDF/Word for this one.
 *
 * Always rendered in Spanish (AC #8), regardless of the requester's chosen
 * interface locale, matching every other payroll/DT exporter's convention.
 */
class EmployeeMasterExporter
{
    /**
     * @var list<string>
     */
    public const FORMATS = ['excel', 'csv'];

    public function __construct(private ReportWriter $writer) {}

    /**
     * @param  Collection<int, User>  $employees
     */
    public function download(string $format, Collection $employees, string $delimiter = ','): Response
    {
        ['fragment' => $fragment, 'filename' => $filename] = $this->prepare($employees);

        return match ($format) {
            'excel' => $this->writer->excel($this->document($fragment), $filename),
            'csv' => $this->writer->csv($fragment, $filename, $delimiter),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * @param  Collection<int, User>  $employees
     * @return array{fragment: string, filename: string}
     */
    private function prepare(Collection $employees): array
    {
        $previousLocale = App::getLocale();
        App::setLocale('es');

        try {
            $rows = $employees->map(fn (User $employee): array => [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'second_last_name' => $employee->second_last_name,
                'rut' => $employee->formatted_rut,
                'email' => $employee->email,
                'personal_email' => $employee->personal_email,
                'phone' => $employee->phone,
                'nationality' => $employee->nationality,
                'gender' => $employee->gender,
                'company' => $employee->company?->social_reason,
                'cost_center' => $employee->costCenter?->name,
                'premise' => $employee->premise?->name,
                'position' => $employee->position?->name,
                'contract_type' => $employee->contract_type?->label(),
                'contract_start_date' => $employee->contract_start_date?->format('d-m-Y'),
                'contract_end_date' => $employee->contract_end_date?->format('d-m-Y'),
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
                'is_active' => $employee->is_active,
            ])->all();

            $fragment = View::make('exports.employees.master', [
                'title' => __('ui.employees.export.title'),
                'rows' => $rows,
            ])->render();

            return ['fragment' => $fragment, 'filename' => Str::slug(__('ui.employees.export.title'))];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * Wrap the report fragment in the full styled HTML document the Excel
     * writer expects. Kept as its own shell (not {@see PayrollSummaryReportExporter}'s
     * `exports.payroll.document`) since the employee master is not a payroll
     * report — it must never risk changing if a payroll export shell does.
     */
    private function document(string $fragment): string
    {
        return View::make('exports.employees.document', [
            'title' => __('ui.employees.export.title'),
            'content' => $fragment,
        ])->render();
    }
}
