<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Duration;
use Illuminate\Support\Carbon;

/**
 * Builds the "Resumen de Remuneraciones por Período" report's rows and
 * consolidated total (RF-1, KOL-20) — the one place that turns
 * {@see PayrollPeriodSummaryService}'s per-employee figures into the flat,
 * formatted shape both the on-screen table (PayrollSummaryReportController)
 * and the export fragment (PayrollSummaryReportExporter) render, so the two
 * can never disagree (AC #3: no figure is calculated a second time here,
 * only formatted and totalled).
 */
class PayrollSummaryReportBuilder
{
    public function __construct(private PayrollPeriodSummaryService $summaries) {}

    /**
     * @param  list<int>  $userIds
     * @return array{rows: list<array<string, mixed>>, total: array<string, mixed>}
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        $summaries = $this->summaries->build($start, $end, $userIds);

        if ($summaries->isEmpty()) {
            return ['rows' => [], 'total' => $this->emptyTotal()];
        }

        $employeesById = User::query()
            ->where('organization_id', CurrentOrganization::id())
            ->whereIn('id', $summaries->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'rut'])
            ->keyBy('id');

        $rows = array_values($employeesById
            ->map(fn (User $employee): array => $this->row($employee, $summaries->get($employee->id)))
            ->all());

        return ['rows' => $rows, 'total' => $this->total($rows)];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $employee, PayrollPeriodSummary $summary): array
    {
        return [
            'userId' => $employee->id,
            'name' => $employee->name,
            'rut' => $employee->formatted_rut,
            'workedHours' => $summary->workedHours->toTimeString(),
            'nonWorkedHours' => $summary->nonWorkedHours->toTimeString(),
            'totalLateness' => $summary->totalLateness->toTimeString(),
            'overtimeOrdinaryDay' => $summary->overtime->ordinaryDayHours->toTimeString(),
            'overtimeSundayOrHoliday' => $summary->overtime->sundayOrHolidayHours->toTimeString(),
            'overtimeCompensatedInRestDays' => $summary->overtime->compensatedInRestDaysHours->toTimeString(),
            'overtimeUnauthorized' => $summary->overtime->unauthorizedHours->toTimeString(),
            'justifiedAbsenceDays' => $summary->justifiedAbsenceDays,
            'unjustifiedAbsenceDays' => $summary->unjustifiedAbsenceDays,
            'sundaysAndHolidaysWorked' => $summary->sundaysAndHolidaysWorked,
            'paidWorkedDays' => $summary->paidDays->workedDays,
            'paidVacationDays' => $summary->paidDays->vacationDays,
            'paidLeaveDays' => $summary->paidDays->paidLeaveDays,
            'nonPaidUnjustifiedAbsenceDays' => $summary->nonPaidDays->unjustifiedAbsenceDays,
            'nonPaidMedicalLeaveDays' => $summary->nonPaidDays->medicalLeaveDays,
            'nonPaidUnpaidLeaveDays' => $summary->nonPaidDays->unpaidLeaveDays,
        ];
    }

    /**
     * The consolidated company total (AC #1): every duration column summed in
     * seconds then reformatted, every count column added directly.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function total(array $rows): array
    {
        $durationColumns = [
            'workedHours', 'nonWorkedHours', 'totalLateness',
            'overtimeOrdinaryDay', 'overtimeSundayOrHoliday',
            'overtimeCompensatedInRestDays', 'overtimeUnauthorized',
        ];

        $countColumns = [
            'justifiedAbsenceDays', 'unjustifiedAbsenceDays', 'sundaysAndHolidaysWorked',
            'paidWorkedDays', 'paidVacationDays', 'paidLeaveDays',
            'nonPaidUnjustifiedAbsenceDays', 'nonPaidMedicalLeaveDays', 'nonPaidUnpaidLeaveDays',
        ];

        $total = ['employeeCount' => count($rows)];

        foreach ($durationColumns as $column) {
            $seconds = array_sum(array_map(
                fn (array $row): int => Duration::fromTimeString($row[$column])->seconds,
                $rows,
            ));
            $total[$column] = Duration::fromSeconds($seconds)->toTimeString();
        }

        foreach ($countColumns as $column) {
            $total[$column] = array_sum(array_column($rows, $column));
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTotal(): array
    {
        return $this->total([]);
    }
}
