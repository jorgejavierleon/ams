<?php

namespace App\Observers;

use App\Models\ShiftDay;

class ShiftDayObserver
{
    /**
     * Handle the ShiftDay "created" event.
     */
    public function created(ShiftDay $shiftDay): void
    {
        $this->recalculateShiftWeekHours($shiftDay);
    }

    /**
     * Handle the ShiftDay "updated" event.
     */
    public function updated(ShiftDay $shiftDay): void
    {
        $this->recalculateShiftWeekHours($shiftDay);
    }

    /**
     * Roll the parent shift's weekly total up from its days' work hours.
     */
    private function recalculateShiftWeekHours(ShiftDay $shiftDay): void
    {
        $shiftDay->load('shift.days');

        // Set directly rather than mass-assign: total_week_hours is a derived,
        // system-managed column and is intentionally not fillable.
        $shift = $shiftDay->shift;
        $shift->total_week_hours = $shift->days->sum('total_work_hours');
        $shift->save();
    }
}
