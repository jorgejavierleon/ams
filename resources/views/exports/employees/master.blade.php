{{-- Maestro de Trabajadores (RF-1, KOL-23). One row per employee matching
     whatever filters were applied on the /employees table — every field is
     read straight off the User model, nothing here is calculated. Inactive
     employees are never dropped by this fragment (AC #4): they are included
     whenever the is_active filter lets them through, and flagged via the
     Activo column instead.

     No <h1> title row here (unlike the other payroll fragments): AC #3 calls
     out this report as the one most likely to be fed straight into another
     system, so the CSV/Excel must open with the column header on row one,
     not a title row first. --}}
@if (count($rows) === 0)
    <p class="legend">{{ __('ui.employees.export.no_rows') }}</p>
@else
    <table>
        <thead>
            <tr>
                <th>{{ __('ui.employees.export.columns.first_name') }}</th>
                <th>{{ __('ui.employees.export.columns.last_name') }}</th>
                <th>{{ __('ui.employees.export.columns.second_last_name') }}</th>
                <th>{{ __('ui.employees.export.columns.rut') }}</th>
                <th>{{ __('ui.employees.export.columns.email') }}</th>
                <th>{{ __('ui.employees.export.columns.personal_email') }}</th>
                <th>{{ __('ui.employees.export.columns.phone') }}</th>
                <th>{{ __('ui.employees.export.columns.nationality') }}</th>
                <th>{{ __('ui.employees.export.columns.gender') }}</th>
                <th>{{ __('ui.employees.export.columns.company') }}</th>
                <th>{{ __('ui.employees.export.columns.cost_center') }}</th>
                <th>{{ __('ui.employees.export.columns.premise') }}</th>
                <th>{{ __('ui.employees.export.columns.position') }}</th>
                <th>{{ __('ui.employees.export.columns.contract_type') }}</th>
                <th>{{ __('ui.employees.export.columns.contract_start_date') }}</th>
                <th>{{ __('ui.employees.export.columns.contract_end_date') }}</th>
                <th>{{ __('ui.employees.export.columns.emergency_contact_name') }}</th>
                <th>{{ __('ui.employees.export.columns.emergency_contact_phone') }}</th>
                <th>{{ __('ui.employees.export.columns.is_active') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['first_name'] ?? '—' }}</td>
                    <td>{{ $row['last_name'] ?? '—' }}</td>
                    <td>{{ $row['second_last_name'] ?? '—' }}</td>
                    <td>{{ $row['rut'] ?? '—' }}</td>
                    <td>{{ $row['email'] ?? '—' }}</td>
                    <td>{{ $row['personal_email'] ?? '—' }}</td>
                    <td>{{ $row['phone'] ?? '—' }}</td>
                    <td>{{ $row['nationality'] ?? '—' }}</td>
                    <td>{{ $row['gender'] ?? '—' }}</td>
                    <td>{{ $row['company'] ?? '—' }}</td>
                    <td>{{ $row['cost_center'] ?? '—' }}</td>
                    <td>{{ $row['premise'] ?? '—' }}</td>
                    <td>{{ $row['position'] ?? '—' }}</td>
                    <td>{{ $row['contract_type'] ?? '—' }}</td>
                    <td>{{ $row['contract_start_date'] ?? '—' }}</td>
                    <td>{{ $row['contract_end_date'] ?? '—' }}</td>
                    <td>{{ $row['emergency_contact_name'] ?? '—' }}</td>
                    <td>{{ $row['emergency_contact_phone'] ?? '—' }}</td>
                    <td>{{ $row['is_active'] ? __('ui.employees.export.yes') : __('ui.employees.export.no') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
