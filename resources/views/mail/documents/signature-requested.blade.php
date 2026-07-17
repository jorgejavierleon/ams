<x-mail::message>
# {{ __('mail.document_signature_requested.heading') }}

{{ __('mail.document_signature_requested.body') }}

- **{{ __('mail.document_signature_requested.document') }}:** {{ $document->title }}
@if ($document->type)
- **{{ __('mail.document_signature_requested.type') }}:** {{ $document->type->label() }}
@endif

{{ config('app.name') }}
</x-mail::message>
