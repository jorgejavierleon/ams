{{-- Reporte de asistencia (Resolución 38, Art. 27 a). A well-formed HTML
     fragment: per worker a header block (empleador / trabajador / lugar de
     prestación) then Fecha / Asistencia / Ausencia / Observaciones. --}}
<h1>{{ $title }}</h1>
@forelse ($report as $worker)
    <table>
        <thead>
            <tr class="block-header">
                <th colspan="2">{{ __('ui.dt.reports.attendance.header.employer') }}: {{ $worker['employer'] ?? '—' }}</th>
                <th>{{ __('ui.dt.reports.attendance.header.employee') }}: {{ $worker['employee'] }}</th>
                <th>{{ __('ui.dt.reports.attendance.header.premise') }}: {{ $worker['premise'] ?? '—' }}</th>
            </tr>
            <tr>
                <th>{{ __('ui.dt.reports.attendance.columns.date') }}</th>
                <th>{{ __('ui.dt.reports.attendance.columns.attendance') }}</th>
                <th>{{ __('ui.dt.reports.attendance.columns.absence') }}</th>
                <th>{{ __('ui.dt.reports.attendance.columns.observations') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($worker['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['attendance'] ? __('ui.dt.reports.attendance.yes') : __('ui.dt.reports.attendance.no') }}</td>
                    <td>
                        @if ($row['absence'] === null)
                            –
                        @elseif ($row['absence'] === 'justified')
                            {{ __('ui.dt.reports.attendance.justified') }}
                        @else
                            {{ __('ui.dt.reports.attendance.unjustified') }}
                        @endif
                    </td>
                    <td>@include('exports.dt.partials.observation', ['observation' => $row['observation']])</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p class="legend">{{ __('ui.dt.reports.attendance.no_workers') }}</p>
@endforelse
