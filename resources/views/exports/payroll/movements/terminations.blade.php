{{-- Bajas: employees whose contract_end_date falls in the period
     (Movimientos del Período, RF-1, KOL-22). Renders even when there are no
     rows, so the sheet is still produced, empty and labelled (AC #6). --}}
<table>
    <thead>
        <tr>
            <th>{{ __('ui.payroll_reports.movements.columns.employee') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.rut') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.position') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.premise') }}</th>
            <th>{{ __('ui.payroll_reports.movements.columns.termination_date') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['employee'] }}</td>
                <td>{{ $row['rut'] ?? '–' }}</td>
                <td>{{ $row['position'] ?? '–' }}</td>
                <td>{{ $row['premise'] ?? '–' }}</td>
                <td>{{ $row['date'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="legend">{{ __('ui.payroll_reports.movements.empty.terminations') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
