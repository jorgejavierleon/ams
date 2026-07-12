<x-mail::message>
# {{ __('mail.leave_approved.heading') }}

{{ __('mail.leave_approved.body') }}

- **{{ __('mail.leave.type') }}:** {{ $leave->type->label() }}
- **{{ __('mail.leave.dates') }}:** {{ $leave->start_date->format('Y-m-d') }} — {{ $leave->end_date->format('Y-m-d') }}
- **{{ __('mail.leave.days') }}:** {{ $leave->business_days_requested }}

<x-mail::button :url="$url">
{{ __('mail.leave.action_my_leaves') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
