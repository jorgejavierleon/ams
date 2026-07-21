{{-- Reporte de jornada diaria (Resolución 38, Art. 27 b). A well-formed HTML
     fragment: per worker a header block then the ten prescribed columns, each
     week closing with the signed totals line (b.12). The exceptional-cycle
     column (b.11) is added only for workers under an exceptional distribution. --}}
@php($na = __('ui.dt.reports.daily.not_applicable'))
<h1>{{ $title }}</h1>
@forelse ($report as $worker)
    @php($showCycle = $worker['exceptionalCycle'] !== null)
    <table>
        <thead>
            <tr class="block-header">
                <th colspan="3">{{ __('ui.dt.reports.attendance.header.employer') }}: {{ $worker['employer'] ?? '—' }}</th>
                <th colspan="3">{{ __('ui.dt.reports.attendance.header.employee') }}: {{ $worker['employee'] }}</th>
                <th colspan="2">{{ __('ui.dt.reports.attendance.header.premise') }}: {{ $worker['premise'] ?? '—' }}</th>
                <th colspan="{{ $showCycle ? 2 : 1 }}">{{ __('ui.dt.reports.daily.header.flexible_band') }}: {{ $worker['hasFlexibleBand'] ? __('ui.dt.reports.daily.yes') : __('ui.dt.reports.daily.no') }}</th>
            </tr>
            <tr>
                <th>{{ __('ui.dt.reports.daily.columns.date') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.journey') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.journey_marks') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.lunch') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.lunch_marks') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.undertime') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.overtime') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.other_marks') }}</th>
                <th>{{ __('ui.dt.reports.daily.columns.observations') }}</th>
                @if ($showCycle)
                    <th>{{ __('ui.dt.reports.daily.columns.exceptional_cycle') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($worker['weeks'] as $week)
                @foreach ($week['days'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['journey'] === null ? '–' : $day['journey']['start'].' – '.$day['journey']['end'] }}</td>
                        <td>
                            @if ($day['journeyMarks']['in'] === null && $day['journeyMarks']['out'] === null)
                                –
                            @else
                                {{ ($day['journeyMarks']['in'] ?? '—').' – '.($day['journeyMarks']['out'] ?? '—') }}
                            @endif
                        </td>
                        <td>{{ $day['lunch'] === null ? $na : $day['lunch']['start'].' – '.$day['lunch']['end'] }}</td>
                        <td>{{ $na }}</td>
                        <td>{{ $day['undertime'] }}</td>
                        <td>{{ $day['overtime'] }}</td>
                        <td>{{ $na }}</td>
                        <td>@include('exports.dt.partials.observation', ['observation' => $day['observation']])</td>
                        @if ($showCycle)
                            <td>{{ $worker['exceptionalCycle'] }}</td>
                        @endif
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>{{ __('ui.dt.reports.daily.week_total') }}</td>
                    <td>{{ $week['totals']['journey'] }}</td>
                    <td>{{ $week['totals']['journeyMarks'] }}</td>
                    <td>{{ $week['totals']['lunch'] }}</td>
                    <td></td>
                    <td>{{ $week['totals']['undertime'] }}</td>
                    <td>{{ $week['totals']['overtime'] }}</td>
                    <td></td>
                    <td>{{ __('ui.dt.reports.daily.compensation') }}: {{ $week['totals']['compensation'] }}</td>
                    @if ($showCycle)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p class="legend">{{ __('ui.dt.reports.daily.no_workers') }}</p>
@endforelse
