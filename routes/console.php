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

// The close-out pass of PRD §7.2, over yesterday for every organization. Late
// enough that an overnight shift has clocked out and its marks have settled;
// re-running it costs nothing, since every date is upserted rather than
// re-inserted.
Schedule::command('overtime:calculate')->dailyAt('04:00');

// The near-expiry alert of KOL-42 AC #3. Once a day is enough: the window is
// measured in days, not hours, and every pacto is only ever notified about
// once (idempotent on `expiry_notified_at`).
Schedule::command('overtime:pacts:notify-expiring')->dailyAt('07:00');

// KOL-47 AC #3: unconsumed rest-day balance past its six-month expiry stops
// being spendable and becomes payable instead (Código del Trabajo art. 32
// §4). Once a day is enough — the window is months, not hours — and the
// sweep is idempotent on `expired_at`.
Schedule::command('overtime:rest-day-balances:sweep-expired')->dailyAt('07:00');
