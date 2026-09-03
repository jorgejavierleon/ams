{{-- Employee import template (KOL-94.8). Headers only, no example data row
     (decision #4): a filled example row risks being re-uploaded as a bogus
     real row under CreateOnly. Column order/labels come straight from
     EmployeeImportSchema's field list — the single source of truth. --}}
<table>
    <thead>
        <tr>
            @foreach ($labels as $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
</table>
