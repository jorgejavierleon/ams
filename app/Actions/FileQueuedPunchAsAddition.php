<?php

namespace App\Actions;

use App\Enums\MarkModificationReason;
use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Managers\MarkManager;
use App\Managers\MarkModificationManager;
use App\Models\MarkModification;
use App\Models\User;
use App\Models\Workday;
use Carbon\CarbonInterface;

/**
 * What happens to a punch the phone queued and could no longer deliver inside
 * the offline cap (`ams.offline_punch_max_age_hours`).
 *
 * Past that cap the regulation's own regularization machinery has already run —
 * Res. 38 Art. 45.1 emails employee and employer thirty minutes after a missed
 * punch, and Art. 40 f) lets the system fill a missing mark `al día siguiente` —
 * so inserting the punch straight into the register would be a second, competing
 * version of a day somebody may already have acted on. Discarding it would throw
 * away the employee's own evidence of when they worked.
 *
 * So it is neither: it is filed through the Art. 39 b) addition pathway, the same
 * bilateral procedure HR uses for a forgotten punch, which carries the Art. 40
 * consequences already built into {@see MarkModificationManager} — the employee
 * is emailed, has 48 hours to object, and silence consolidates the addition into
 * a real mark.
 */
class FileQueuedPunchAsAddition
{
    public function __construct(
        private readonly MarkModificationManager $modifications,
        private readonly MarkManager $marks,
    ) {}

    /**
     * File the over-age punch against the day it was made. Returns null when
     * that day already carries a pending request for the same punch type — the
     * duplicate guard, which is also what makes a retry of an over-age punch
     * harmless.
     */
    public function handle(User $user, MarkType $type, CarbonInterface $deviceDateTime): ?MarkModification
    {
        $workday = $this->workdayFor($user, $deviceDateTime);

        return $this->modifications->createModification(
            $workday,
            $type,
            $deviceDateTime->format('H:i:s'),
            // Art. 39 b) names `fallas del sistema` among the causes an addition
            // answers, and a queue that could not reach the register for a day
            // is one — not an employee who forgot to punch.
            MarkModificationReason::SystemError,
            __('ui.marks.api.offline.modification_notes', [
                'captured' => $deviceDateTime->format('d-m-Y H:i:s'),
                'synced' => now($user->timezone ?? config('app.timezone_display'))->format('d-m-Y H:i:s'),
            ]),
            $deviceDateTime,
        );
    }

    /**
     * The computed day the addition attaches to, created when the calculator has
     * not produced one yet. A day with no marks at all is only ever computed
     * once something exists to compute it from, and an over-age queued punch can
     * easily be the first thing that does.
     */
    private function workdayFor(User $user, CarbonInterface $deviceDateTime): Workday
    {
        $workday = Workday::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $deviceDateTime)
            ->first();

        if ($workday !== null) {
            return $workday;
        }

        $shift = $this->marks->getShiftForDate($user, $deviceDateTime);

        return Workday::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'company_id' => $user->company_id,
            'premise_id' => $user->premise_id,
            'date' => $deviceDateTime->toDateString(),
            'shift_id' => $shift['shift_id'] ?? null,
            'shift_start_time' => $shift['start_time'] ?? null,
            'shift_end_time' => $shift['end_time'] ?? null,
            // The day holds no marks yet: scheduled and unattended is an
            // absence, unscheduled and unattended is nothing at all. Approving
            // the addition recalculates the row from its marks either way.
            'status' => $shift === null ? null : WorkdayStatus::Absent,
        ]);
    }
}
