<x-mail::message>
# {{ __('mail.overtime_request_submitted.heading') }}

{{ __('mail.overtime_request_submitted.body', ['employee' => $overtimeRequest->user->name]) }}

- **{{ __('mail.overtime_request.date') }}:** {{ $overtimeRequest->date->format('Y-m-d') }}
- **{{ __('mail.overtime_request.hours') }}:** {{ substr($overtimeRequest->requested_hours, 0, 5) }}

<x-mail::button :url="$url">
{{ __('mail.overtime_request_submitted.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
