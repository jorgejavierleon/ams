<x-mail::message>
# {{ __('mail.overtime_pending_alert.heading') }}

{{ __('mail.overtime_pending_alert.body', ['count' => $staleCount, 'days' => $thresholdDays]) }}

- **{{ __('mail.overtime_pending_alert.oldest') }}:** {{ __('mail.overtime_pending_alert.days', ['days' => $oldestDaysPending]) }}

<x-mail::button :url="$url">
{{ __('mail.overtime_pending_alert.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
