<x-mail::message>
# {{ __('mail.rest_day_balance_accrued.heading') }}

{{ __('mail.rest_day_balance_accrued.body') }}

<x-mail::table>
| {{ __('mail.rest_day_balance_accrued.hours') }} | {{ __('mail.rest_day_balance_accrued.expiry_date') }} |
| :--- | :--- |
@foreach ($balances as $balance)
| {{ (string) $balance->remaining() }} | {{ $balance->expiry_date->format('Y-m-d') }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$url">
{{ __('mail.rest_day_balance_accrued.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
