{{-- Reporte de incidentes técnicos (Resolución 38, Art. 27 f). A well-formed
     HTML fragment: a per-employer log of attendance-system outages — start, end,
     duration and description. --}}
<h1>{{ $title }}</h1>
<table>
    <thead>
        <tr>
            <th>{{ __('ui.dt.reports.incidents.columns.start_time') }}</th>
            <th>{{ __('ui.dt.reports.incidents.columns.end_time') }}</th>
            <th>{{ __('ui.dt.reports.incidents.columns.duration') }}</th>
            <th>{{ __('ui.dt.reports.incidents.columns.description') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($report as $incident)
            <tr>
                <td>{{ $incident['start_time'] }}</td>
                <td>{{ $incident['end_time'] ?? __('ui.dt.reports.incidents.ongoing') }}</td>
                <td>{{ $incident['duration'] ?? '—' }}</td>
                <td>{{ $incident['description'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="legend">{{ __('ui.dt.reports.incidents.empty') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
