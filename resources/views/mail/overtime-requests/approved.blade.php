<x-mail::message>
# {{ __('mail.overtime_request_approved.heading') }}

{{ __('mail.overtime_request_approved.body') }}

- **{{ __('mail.overtime_request.date') }}:** {{ $overtimeRequest->date->format('Y-m-d') }}
- **{{ __('mail.overtime_request.hours') }}:** {{ substr($overtimeRequest->requested_hours, 0, 5) }}

<x-mail::button :url="$url">
{{ __('mail.overtime_request.action_my_requests') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
