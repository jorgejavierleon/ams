<x-mail::message>
# {{ __('mail.document_fully_signed.heading') }}

{{ __('mail.document_fully_signed.body') }}

- **{{ __('mail.document_fully_signed.document') }}:** {{ $title }}

{{ config('app.name') }}
</x-mail::message>
