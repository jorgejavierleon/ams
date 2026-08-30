{{-- Detalle Semanal por Trabajador (RF-1, KOL-21). One table per ISO week,
     real versus teórica entrada/salida/colación for a single worker; every
     figure is read straight off $weeks, computed once by
     WeeklyDetailReportBuilder — nothing here is calculated. --}}
<h1>{{ $title }}</h1>
@if ($employee === null)
    <p class="legend">{{ __('ui.payroll_reports.weekly_detail.select_one_required') }}</p>
@else
    <p>{{ $employee['name'] }}{{ $employee['rut'] ? ' - '.$employee['rut'] : '' }}</p>
    @foreach ($weeks as $week)
        <table>
            <thead>
                <tr class="group-header">
                    <th rowspan="2">{{ __('ui.payroll_reports.weekly_detail.columns.day') }}</th>
                    <th rowspan="2">{{ __('ui.payroll_reports.weekly_detail.columns.status') }}</th>
                    <th colspan="3">{{ __('ui.payroll_reports.weekly_detail.groups.entry') }}</th>
                    <th colspan="3">{{ __('ui.payroll_reports.weekly_detail.groups.exit') }}</th>
                    <th colspan="2">{{ __('ui.payroll_reports.weekly_detail.groups.lunch') }}</th>
                    <th rowspan="2">{{ __('ui.payroll_reports.weekly_detail.columns.observation') }}</th>
                </tr>
                <tr>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.real') }}</th>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.theoretical') }}</th>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.difference') }}</th>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.real') }}</th>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.theoretical') }}</th>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.difference') }}</th>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.theoretical_start') }}</th>
                    <th>{{ __('ui.payroll_reports.weekly_detail.columns.theoretical_end') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($week['days'] as $day)
                    <tr>
                        <td>{{ ucfirst($day['weekday_label']) }} {{ $day['date_label'] }}</td>
                        <td>{{ $day['status_label'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['entry']['real'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['entry']['theoretical'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['entry']['difference'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['exit']['real'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['exit']['theoretical'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['exit']['difference'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['lunch']['theoretical_start'] ?? '—' }}</td>
                        <td class="numeric">{{ $day['lunch']['theoretical_end'] ?? '—' }}</td>
                        <td>
                            @if ($day['leave'] !== null)
                                {{ $day['leave']['type_label'] }}
                            @endif
                            @if ($day['has_pending_modification'])
                                {{ __('ui.payroll_reports.weekly_detail.pending_modification') }}
                            @elseif ($day['has_approved_modification'])
                                {{ __('ui.payroll_reports.weekly_detail.approved_modification') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endif
