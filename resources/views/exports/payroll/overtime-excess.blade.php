{{-- Excesos de Jornada y HHEE (RF-1, KOL-24). One table per Monday-Sunday
     week, pactada (per pay bucket) versus no pactada per employee, and the
     applicable weekly overtime cap; every figure is read straight off
     $weeks, computed once by OvertimeExcessReportBuilder — nothing here is
     calculated. --}}
<h1>{{ $title }}</h1>
@if (count($weeks) === 0)
    <p class="legend">{{ __('ui.payroll_reports.overtime_excess.no_rows') }}</p>
@else
    @foreach ($weeks as $week)
        <h2>{{ __('ui.payroll_reports.overtime_excess.week_label', ['start' => $week['start'], 'end' => $week['end']]) }}</h2>
        <p class="legend">
            {{ __('ui.payroll_reports.overtime_excess.legal_basis', ['hours' => $week['weeklyOvertimeCapHours'], 'reference' => $week['legalReference']]) }}
            @if ($week['employeesOverCapCount'] > 0)
                — {{ __('ui.payroll_reports.overtime_excess.employees_over_cap', ['count' => $week['employeesOverCapCount']]) }}
            @endif
        </p>
        <table>
            <thead>
                <tr class="group-header">
                    <th colspan="2">{{ __('ui.payroll_reports.overtime_excess.groups.employee') }}</th>
                    <th colspan="4">{{ __('ui.payroll_reports.overtime_excess.groups.pactada') }}</th>
                    <th colspan="2">{{ __('ui.payroll_reports.overtime_excess.groups.no_pactada') }}</th>
                </tr>
                <tr>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.employee') }}</th>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.rut') }}</th>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.ordinary_day') }}</th>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.sunday_holiday') }}</th>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.compensated_rest_days') }}</th>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.payable_total') }}</th>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.unauthorized') }}</th>
                    <th>{{ __('ui.payroll_reports.overtime_excess.columns.cap_exceeded') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($week['rows'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['rut'] ?? '—' }}</td>
                        <td class="numeric">{{ $row['ordinaryDayHours'] }}</td>
                        <td class="numeric">{{ $row['sundayOrHolidayHours'] }}</td>
                        <td class="numeric">{{ $row['compensatedInRestDaysHours'] }}</td>
                        <td class="numeric">{{ $row['payableTotalHours'] }}</td>
                        <td class="numeric">{{ $row['unauthorizedHours'] }}</td>
                        <td>{{ $row['capExceeded'] ? __('ui.payroll_reports.overtime_excess.cap_exceeded_yes') : __('ui.payroll_reports.overtime_excess.cap_exceeded_no') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2">{{ __('ui.payroll_reports.overtime_excess.week_total') }} ({{ __('ui.payroll_reports.summary.employee_count', ['count' => $week['total']['employeeCount']]) }})</td>
                    <td class="numeric">{{ $week['total']['ordinaryDayHours'] }}</td>
                    <td class="numeric">{{ $week['total']['sundayOrHolidayHours'] }}</td>
                    <td class="numeric">{{ $week['total']['compensatedInRestDaysHours'] }}</td>
                    <td class="numeric">{{ $week['total']['payableTotalHours'] }}</td>
                    <td class="numeric">{{ $week['total']['unauthorizedHours'] }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endif
