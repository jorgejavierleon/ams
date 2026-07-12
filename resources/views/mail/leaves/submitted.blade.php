<x-mail::message>
# {{ __('mail.leave_submitted.heading') }}

{{ __('mail.leave_submitted.body', ['employee' => $leave->user->name]) }}

- **{{ __('mail.leave.type') }}:** {{ $leave->type->label() }}
- **{{ __('mail.leave.dates') }}:** {{ $leave->start_date->format('Y-m-d') }} — {{ $leave->end_date->format('Y-m-d') }}
- **{{ __('mail.leave.days') }}:** {{ $leave->business_days_requested }}

<x-mail::button :url="$url">
{{ __('mail.leave_submitted.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
