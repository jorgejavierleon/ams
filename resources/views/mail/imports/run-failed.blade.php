<x-mail::message>
# {{ __('mail.import_run_failed.heading') }}

{{ __('mail.import_run_failed.body') }}

<x-mail::button :url="$url">
{{ __('mail.import_run_failed.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
