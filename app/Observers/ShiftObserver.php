<?php

namespace App\Observers;

use App\Models\Shift;

class ShiftObserver
{
    /**
     * Seed the shift with a default day for every weekday.
     *
     * Uses the SQL WEEKDAY format (0 = Monday ... 6 = Sunday). Saturday and
     * Sunday are marked non-working (`is_free`) by default.
     */
    public function created(Shift $shift): void
    {
        for ($weekday = 0; $weekday < 7; $weekday++) {
            $shift->days()->create([
                'weekday' => $weekday,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'lunch_start_time' => '12:00:00',
                'lunch_end_time' => '13:00:00',
                'is_free' => $weekday === 5 || $weekday === 6,
            ]);
        }
    }
}
