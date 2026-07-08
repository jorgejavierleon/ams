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

];
