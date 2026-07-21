{{-- Reporte de días domingo y/o días festivos (Resolución 38, Art. 27 c). A
     well-formed HTML fragment: per worker a header block, the five prescribed
     columns grouped by month with a per-month subtotal (c.7) and a final period
     total (c.8), or the legend for a worker whose journey never falls on such
     days. --}}
<h1>{{ $title }}</h1>
@forelse ($report as $worker)
    <table>
        <thead>
            <tr class="block-header">
                <th colspan="2">{{ __('ui.dt.reports.attendance.header.employer') }}: {{ $worker['employer'] ?? '—' }}</th>
                <th>{{ __('ui.dt.reports.attendance.header.employee') }}: {{ $worker['employee'] }}</th>
                <th>{{ __('ui.dt.reports.attendance.header.premise') }}: {{ $worker['premise'] ?? '—' }}</th>
                <th>{{ __('ui.dt.reports.sundays.header.position') }}: {{ $worker['position'] ?? '—' }}</th>
            </tr>
            @if ($worker['additionalSundays'])
                <tr class="block-header">
                    <th colspan="5">{{ __('ui.dt.reports.sundays.additional_flag') }}</th>
                </tr>
            @endif
            <tr>
                <th>{{ __('ui.dt.reports.sundays.columns.additional') }}</th>
                <th>{{ __('ui.dt.reports.sundays.columns.date') }}</th>
                <th>{{ __('ui.dt.reports.sundays.columns.attendance') }}</th>
                <th>{{ __('ui.dt.reports.sundays.columns.absence') }}</th>
                <th>{{ __('ui.dt.reports.sundays.columns.observations') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($worker['months'] as $month)
                @foreach ($month['rows'] as $row)
                    <tr>
                        <td>{{ $worker['additionalSundays'] ? __('ui.dt.reports.sundays.yes') : __('ui.dt.reports.sundays.no') }}</td>
                        <td>{{ $row['date'] }}@if ($row['dayType'] === 'holiday' && $row['holiday'] !== null) ({{ $row['holiday'] }})@endif</td>
                        <td>{{ $row['attendance'] ? __('ui.dt.reports.sundays.yes') : __('ui.dt.reports.sundays.no') }}</td>
                        <td>
                            @if ($row['absence'] === null)
                                –
                            @elseif ($row['absence'] === 'justified')
                                {{ __('ui.dt.reports.sundays.justified') }}
                            @else
                                {{ __('ui.dt.reports.sundays.unjustified') }}
                            @endif
                        </td>
                        <td>
                            @if ($row['observation'] === null)
                            @elseif ($row['observation']['kind'] === 'leave')
                                {{ __('ui.leaves.types.'.$row['observation']['type']) }}
                            @else
                                {{ $row['observation']['name'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td></td>
                    <td>{{ __('ui.dt.reports.sundays.month_total', ['month' => $month['label']]) }}</td>
                    <td>{{ $month['worked'] }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="legend">{{ __('ui.dt.reports.sundays.no_sundays') }}</td>
                </tr>
            @endforelse
            @if (! empty($worker['months']))
                <tr class="grand-total-row">
                    <td></td>
                    <td>{{ __('ui.dt.reports.sundays.period_total') }}</td>
                    <td>{{ $worker['total'] }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
@empty
    <p class="legend">{{ __('ui.dt.reports.sundays.no_workers') }}</p>
@endforelse
