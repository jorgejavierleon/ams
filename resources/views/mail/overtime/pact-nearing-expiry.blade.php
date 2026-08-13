<x-mail::message>
# {{ __('mail.overtime_pact_nearing_expiry.heading') }}

{{ __('mail.overtime_pact_nearing_expiry.body', ['employee' => $pact->user->name, 'date' => $pact->end_date->format('Y-m-d')]) }}

- **{{ __('mail.overtime_pact.employee') }}:** {{ $pact->user->name }}
- **{{ __('mail.overtime_pact.end_date') }}:** {{ $pact->end_date->format('Y-m-d') }}

<x-mail::button :url="$url">
{{ __('mail.overtime_pact_nearing_expiry.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
