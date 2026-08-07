<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal working-hour limits (Chile)
    |--------------------------------------------------------------------------
    |
    | Deliberately absent. `max_weekly_hours` and `max_daily_hours` used to live
    | here as scalars, which cannot be right for more than one date at a time:
    | Ley 21.561 moves the ordinary week from 45 to 44, 42 and 40 hours on three
    | separate dates, and a report that reads a single number gives a closed
    | period a different answer after the law changes than it gave the first
    | time. The limits are now date-versioned rows resolved through
    | App\Services\LegalHourLimits, which requires the date it is asked about.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Mark-modification review window
    |--------------------------------------------------------------------------
    |
    | How long an employee has to approve or decline a requested correction to
    | one of their attendance marks before the request lapses. After this many
    | hours a still-pending modification is considered expired and can no longer
    | be actioned from the public review page.
    |
    */

    'mark_modification_timeout_hours' => 48,

    /*
    |--------------------------------------------------------------------------
    | Offline punch queue
    |--------------------------------------------------------------------------
    |
    | Resolución 38 Art. 10 lets a device capture a punch with no connection and
    | transmit it when the signal returns. `offline_punch_max_age_hours` is how
    | long that transmission may take before the punch stops being a delayed
    | delivery: past it, Art. 45.1's missed-punch alert has gone out and Art.
    | 40 f) may already have filled the gap, so the punch is filed as an Art. 39
    | b) addition for review instead of inserted.
    |
    | `offline_punch_future_tolerance_minutes` is the only slack given to a
    | device clock that runs ahead. A punch cannot be made in a future the
    | register has not reached (Art. 11), and a wide tolerance here is a way to
    | choose your own arrival time.
    |
    */

    'offline_punch_max_age_hours' => 24,

    'offline_punch_future_tolerance_minutes' => 5,

];
