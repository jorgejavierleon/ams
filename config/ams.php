<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal working-hour limits (Chile)
    |--------------------------------------------------------------------------
    |
    | Maximum ordinary hours allowed per week and per day under the Chilean
    | Código del Trabajo (arts. 22 & 28). Used to flag shifts that exceed the
    | legal maximum. The weekly cap is being reduced by law over time, so it is
    | kept configurable rather than hardcoded.
    |
    */

    'max_weekly_hours' => 45,
    'max_daily_hours' => 10,

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
