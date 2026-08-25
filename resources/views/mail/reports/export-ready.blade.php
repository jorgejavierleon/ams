<x-mail::message>
# {{ __('mail.report_export_ready.heading') }}

{{ __('mail.report_export_ready.body') }}

<x-mail::button :url="$url">
{{ __('mail.report_export_ready.action') }}
</x-mail::button>

{{ __('mail.report_export_ready.expiry', ['minutes' => $expiryMinutes]) }}

{{ __('mail.report_export_ready.note') }}

{{ config('app.name') }}
</x-mail::message>
