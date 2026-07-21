{{-- Reporte de modificaciones y/o alteraciones de turnos (Resolución 38,
     Art. 27 d). A well-formed HTML fragment: per worker a header block then the
     nine prescribed columns, or the legend justifying the absence of changes. --}}
<h1>{{ $title }}</h1>
@forelse ($report as $worker)
    <table>
        <thead>
            <tr class="block-header">
                <th colspan="3">{{ __('ui.dt.reports.shift-changes.header.employer') }}: {{ $worker['employer'] ?? '—' }}</th>
                <th colspan="3">{{ __('ui.dt.reports.shift-changes.header.employee') }}: {{ $worker['employee'] }}</th>
                <th colspan="3">{{ __('ui.dt.reports.shift-changes.header.premise') }}: {{ $worker['premise'] ?? '—' }}</th>
            </tr>
            <tr>
                <th>{{ __('ui.dt.reports.shift-changes.columns.old_start_date') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.old_shift') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.old_extension') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.notification_date') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.new_start_date') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.new_shift') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.new_extension') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.requested_by') }}</th>
                <th>{{ __('ui.dt.reports.shift-changes.columns.observations') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($worker['rows'] as $row)
                <tr>
                    <td>{{ $row['oldStartDate'] ?? '–' }}</td>
                    <td>{{ $row['oldShift'] ?? '–' }}</td>
                    <td>{{ $row['oldExtension'] === null ? '–' : __('ui.shifts.types.'.$row['oldExtension']) }}</td>
                    <td>{{ $row['notificationDate'] ?? '–' }}</td>
                    <td>{{ $row['newStartDate'] }}</td>
                    <td>{{ $row['newShift'] }}</td>
                    <td>{{ __('ui.shifts.types.'.$row['newExtension']) }}</td>
                    <td>{{ __('ui.dt.reports.shift-changes.requested_by.'.$row['requestedBy']) }}</td>
                    <td>{{ $row['observation'] ?? '–' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="legend">
                        {{ $worker['emptyReason'] === 'fixed-journey'
                            ? __('ui.dt.reports.shift-changes.fixed_journey')
                            : __('ui.dt.reports.shift-changes.no_changes') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@empty
    <p class="legend">{{ __('ui.dt.reports.shift-changes.no_workers') }}</p>
@endforelse
