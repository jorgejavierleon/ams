<?php

namespace App\Http\Controllers\Api;

use App\Enums\PunchState;
use App\Http\Controllers\Controller;
use App\Http\Resources\UpcomingShiftsResource;
use App\Managers\MarkManager;
use App\Models\User;
use App\Services\ShiftScheduleResolver;
use App\Services\TimeZoneService;
use App\Support\UpcomingShiftsSummary;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The Jornada tab's Próximos screen (kolvi-mobile KMO-32) in a single request:
 * today's shift and punch status, then the schedule for the days after it.
 *
 * Gated on `ViewOwn:Workday`, unlike `/me/today` — that endpoint stays
 * reachable for an admin who does not punch, but there is nothing on this
 * screen for someone who cannot view their own workday at all.
 */
class UpcomingShiftsController extends Controller
{
    /** The PRD's own default: two weeks out. */
    private const DEFAULT_DAYS = 14;

    /** A horizon nobody asks a phone screen to scroll past. */
    private const MAX_DAYS = 30;

    public function __invoke(
        Request $request,
        ShiftScheduleResolver $resolver,
        MarkManager $marks,
        TimeZoneService $timeZone,
    ): UpcomingShiftsResource {
        /** @var User $user */
        $user = $request->user();

        // The employee's own wall-clock day, like TodayController — what
        // "today" means on this screen is where the employee is standing.
        $today = Carbon::now($timeZone->getUserTimezone($user));

        $horizon = min(self::MAX_DAYS, max(1, (int) $request->query('days', self::DEFAULT_DAYS)));

        $todayDay = $resolver->resolve($user, $today->copy(), $today->copy())->first();

        $days = $resolver->resolve(
            $user,
            $today->copy()->addDay(),
            $today->copy()->addDays($horizon),
        );

        return new UpcomingShiftsResource(new UpcomingShiftsSummary(
            date: $today,
            today: $todayDay,
            punchState: $this->punchState($user, $today, $marks),
            days: $days,
        ));
    }

    /**
     * Where the employee is in today's day, or null for one who does not
     * punch at all — the same gate and the same reasoning as
     * {@see TodayController::punchState()}.
     */
    private function punchState(User $user, CarbonInterface $today, MarkManager $marks): ?PunchState
    {
        if (! $user->getAllPermissions()->pluck('name')->contains('ClockOwn:Mark')) {
            return null;
        }

        return $marks->punchStateForDate($user, $today);
    }
}
