<x-mail::message>
# {{ __('mail.document_signature_verification_code.heading') }}

{{ __('mail.document_signature_verification_code.body') }}

- **{{ __('mail.document_signature_verification_code.document') }}:** {{ $title }}

<x-mail::panel>
{{ $code }}
</x-mail::panel>

{{ __('mail.document_signature_verification_code.expiry') }}

{{ config('app.name') }}
</x-mail::message>
