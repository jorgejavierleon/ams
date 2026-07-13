<x-mail::message>
# {{ __('mail.mark_created.heading') }}

{{ __('mail.mark_created.body') }}

- **{{ __('mail.mark_created.type') }}:** {{ $type }}
- **{{ __('mail.mark_created.date_time') }}:** {{ $dateTime }}
- **{{ __('mail.mark_created.checksum') }}:** {{ $checksum }}

{{ config('app.name') }}
</x-mail::message>
