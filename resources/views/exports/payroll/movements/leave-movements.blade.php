{{-- Shared fragment for the three leave-based sheets (inicio de licencias,
     fin de licencias, vacaciones aprobadas) — same shape, different
     pre-filtered $rows and empty legend (Movimientos del Período, RF-1,
     KOL-22). Renders even when there are no rows, so the sheet is still
     produced, empty and labelled (AC #6). --}}
<table>
    <thead>
        <tr>
            <th>{{ __('ui.payroll_reports.movements.columns.employee') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.rut') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.type') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.start_date') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.end_date') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.days') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['employee'] }}</td>
                <td>{{ $row['rut'] ?? '–' }}</td>
                <td>{{ $row['type'] }}</td>
                <td>{{ $row['startDate'] }}</td>
                <td>{{ $row['endDate'] }}</td>
                <td class="numeric">{{ $row['days'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="legend">{{ $emptyMessage }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
