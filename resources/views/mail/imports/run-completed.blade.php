<x-mail::message>
# {{ __('mail.import_run_completed.heading') }}

{{ __('mail.import_run_completed.body') }}

- {{ __('mail.import_run_completed.created', ['count' => $createdCount]) }}
- {{ __('mail.import_run_completed.updated', ['count' => $updatedCount]) }}
- {{ __('mail.import_run_completed.skipped', ['count' => $skippedCount]) }}
- {{ __('mail.import_run_completed.errored', ['count' => $erroredCount]) }}

<x-mail::button :url="$url">
{{ __('mail.import_run_completed.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
