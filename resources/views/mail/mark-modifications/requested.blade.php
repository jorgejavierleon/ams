<x-mail::message>
# {{ __('mail.mark_modification_requested.heading') }}

{{ __('mail.mark_modification_requested.body') }}

- **{{ __('mail.mark_modification_requested.mark_type') }}:** {{ $markModification->mark_type?->label() }}
- **{{ __('mail.mark_modification_requested.original') }}:** {{ $markModification->mark?->date_time?->format('d-m-Y H:i') ?? __('mail.mark_modification_requested.no_mark') }}
- **{{ __('mail.mark_modification_requested.new') }}:** {{ $markModification->date_time->format('d-m-Y H:i') }}
- **{{ __('mail.mark_modification_requested.reason') }}:** {{ $markModification->reason?->label() }}
@if ($markModification->notes)
- **{{ __('mail.mark_modification_requested.notes') }}:** {{ $markModification->notes }}
@endif

{{ __('mail.mark_modification_requested.auto_approve') }}

<x-mail::button :url="$reviewUrl">
{{ __('mail.mark_modification_requested.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
