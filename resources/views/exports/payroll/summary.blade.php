{{-- Resumen de Remuneraciones por Período (RF-1, KOL-20). One row per
     employee plus a consolidated company total; every figure is read
     straight off $rows/$total, computed once by PayrollSummaryReportBuilder
     — nothing here is calculated (AC #3). --}}
<h1>{{ $title }}</h1>
@if (count($rows) === 0)
    <p class="legend">{{ __('ui.payroll_reports.summary.no_rows') }}</p>
@else
    <table>
        <thead>
            <tr class="group-header">
                <th colspan="2">{{ __('ui.payroll_reports.summary.groups.employee') }}</th>
                <th colspan="3">{{ __('ui.payroll_reports.summary.groups.hours') }}</th>
                <th colspan="4">{{ __('ui.payroll_reports.summary.groups.overtime') }}</th>
                <th colspan="2">{{ __('ui.payroll_reports.summary.groups.absences') }}</th>
                <th>{{ __('ui.payroll_reports.summary.groups.sundays_holidays') }}</th>
                <th colspan="3">{{ __('ui.payroll_reports.summary.groups.paid_days') }}</th>
                <th colspan="3">{{ __('ui.payroll_reports.summary.groups.non_paid_days') }}</th>
            </tr>
            <tr>
                <th>{{ __('ui.payroll_reports.summary.columns.employee') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.rut') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.worked_hours') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.non_worked_hours') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.lateness') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.overtime_ordinary') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.overtime_sunday_holiday') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.overtime_compensated') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.overtime_unauthorized') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.justified_absences') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.unjustified_absences') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.sundays_holidays_worked') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.paid_worked_days') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.paid_vacation_days') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.paid_leave_days') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.non_paid_unjustified_days') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.non_paid_medical_days') }}</th>
                <th>{{ __('ui.payroll_reports.summary.columns.non_paid_unpaid_days') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['rut'] ?? '—' }}</td>
                    <td class="numeric">{{ $row['workedHours'] }}</td>
                    <td class="numeric">{{ $row['nonWorkedHours'] }}</td>
                    <td class="numeric">{{ $row['totalLateness'] }}</td>
                    <td class="numeric">{{ $row['overtimeOrdinaryDay'] }}</td>
                    <td class="numeric">{{ $row['overtimeSundayOrHoliday'] }}</td>
                    <td class="numeric">{{ $row['overtimeCompensatedInRestDays'] }}</td>
                    <td class="numeric">{{ $row['overtimeUnauthorized'] }}</td>
                    <td class="numeric">{{ $row['justifiedAbsenceDays'] }}</td>
                    <td class="numeric">{{ $row['unjustifiedAbsenceDays'] }}</td>
                    <td class="numeric">{{ $row['sundaysAndHolidaysWorked'] }}</td>
                    <td class="numeric">{{ $row['paidWorkedDays'] }}</td>
                    <td class="numeric">{{ $row['paidVacationDays'] }}</td>
                    <td class="numeric">{{ $row['paidLeaveDays'] }}</td>
                    <td class="numeric">{{ $row['nonPaidUnjustifiedAbsenceDays'] }}</td>
                    <td class="numeric">{{ $row['nonPaidMedicalLeaveDays'] }}</td>
                    <td class="numeric">{{ $row['nonPaidUnpaidLeaveDays'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2">{{ __('ui.payroll_reports.summary.total_row') }} ({{ __('ui.payroll_reports.summary.employee_count', ['count' => $total['employeeCount']]) }})</td>
                <td class="numeric">{{ $total['workedHours'] }}</td>
                <td class="numeric">{{ $total['nonWorkedHours'] }}</td>
                <td class="numeric">{{ $total['totalLateness'] }}</td>
                <td class="numeric">{{ $total['overtimeOrdinaryDay'] }}</td>
                <td class="numeric">{{ $total['overtimeSundayOrHoliday'] }}</td>
                <td class="numeric">{{ $total['overtimeCompensatedInRestDays'] }}</td>
                <td class="numeric">{{ $total['overtimeUnauthorized'] }}</td>
                <td class="numeric">{{ $total['justifiedAbsenceDays'] }}</td>
                <td class="numeric">{{ $total['unjustifiedAbsenceDays'] }}</td>
                <td class="numeric">{{ $total['sundaysAndHolidaysWorked'] }}</td>
                <td class="numeric">{{ $total['paidWorkedDays'] }}</td>
                <td class="numeric">{{ $total['paidVacationDays'] }}</td>
                <td class="numeric">{{ $total['paidLeaveDays'] }}</td>
                <td class="numeric">{{ $total['nonPaidUnjustifiedAbsenceDays'] }}</td>
                <td class="numeric">{{ $total['nonPaidMedicalLeaveDays'] }}</td>
                <td class="numeric">{{ $total['nonPaidUnpaidLeaveDays'] }}</td>
            </tr>
        </tfoot>
    </table>
@endif
