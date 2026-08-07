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

];
