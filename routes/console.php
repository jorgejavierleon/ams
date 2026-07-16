<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Consolidate mark modifications the employee never opposed within the 48h
// window (Resolución 38, art. 40 d). Frequent enough that a consolidated
// correction is reflected within minutes of the window closing.
Schedule::command('mark-modifications:approve-overdue')->everyTenMinutes();
