{{-- The "Observaciones" cell for the attendance and daily reports (Art. 27 a.5,
     b.10). Matches the on-screen wording: "Libre" for a free shift day,
     "Feriado" for a holiday, otherwise the leave type. --}}
@if ($observation === null)
@elseif ($observation['kind'] === 'free')
    {{ __('ui.dt.reports.attendance.observations.free') }}
@elseif ($observation['kind'] === 'holiday')
    {{ __('ui.dt.reports.attendance.observations.holiday') }}
@else
    {{ __('ui.leaves.types.'.$observation['type']) }}
@endif
