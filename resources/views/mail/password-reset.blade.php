<x-mail::message>
# {{ __('mail.password_reset.heading') }}

{{ __('mail.password_reset.body') }}

<x-mail::button :url="$url">
{{ __('mail.password_reset.action') }}
</x-mail::button>

{{ __('mail.password_reset.expiry', ['minutes' => $minutes]) }}

{{ __('mail.password_reset.warning') }}

{{ config('app.name') }}
</x-mail::message>
