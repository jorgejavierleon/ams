<?php

namespace App\Http\Controllers\Api;

use App\Enums\MarkType;
use App\Enums\PunchState;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodayResource;
use App\Managers\MarkManager;
use App\Models\Mark;
use App\Models\ShiftDay;
use App\Models\User;
use App\Models\Workday;
use App\Services\TimeZoneService;
use App\Support\TodaySummary;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The employee mobile app's home screen (Marcaje tab) in a single request:
 * today's shift, where the employee is in their day, and the week so far.
 *
 * One aggregate rather than three calls is the point. The app's goal is
 * time-to-punch under ten seconds from open, and a screen that fanned out to the
 * shift, the marks and the week separately would spend three round trips on a
 * warehouse connection before the punch button was live.
 *
 * Every read is a constant number of queries: nothing here loops over a
 * collection issuing more.
 */
class TodayController extends Controller
{
    public function __invoke(Request $request, MarkManager $marks, TimeZoneService $timeZone): TodayResource
    {
        /** @var User $user */
        $user = $request->user();

        // The employee's own wall-clock day, not the server's: what "today"
        // means on the home screen is where the employee is standing.
        $today = Carbon::now($timeZone->getUserTimezone($user));

        $assignment = $marks->getShiftAssignmentForDate($user, $today);
        $shiftDay = $assignment === null
            ? null
            : $marks->getShiftDayForAssignment($assignment, $today);

        // The weekly total belongs to the assignment, not to the scheduled day,
        // so a free day still reports the week's progress against its contract.
        $assignment?->loadMissing('shift');

        return new TodayResource(new TodaySummary(
            date: $today,
            shiftDay: $shiftDay,
            premiseLabel: $shiftDay === null ? null : $this->premiseLabel($user, $shiftDay),
            punchState: $this->punchState($user, $today),
            workedHours: $this->weekWorkedHours($user, $today),
            contractedHours: $this->nonNegativeHours($assignment?->shift?->total_week_hours),
        ));
    }

    /**
     * Where the shift is worked. The employee's premise names the card; a
     * shift's own name stands in for an employee not attached to one, since the
     * app requires a label and an empty card reads as a broken response.
     */
    private function premiseLabel(User $user, ShiftDay $shiftDay): ?string
    {
        return $user->premise?->name ?? $shiftDay->shift?->name;
    }

    /**
     * Where the employee is in their day, or null when they do not punch at
     * all.
     *
     * Gated on the permission the user actually holds rather than on `can()`,
     * which the super-admin gate would answer for — the same reading the web
     * dashboard's clock widget and the `permission:` middleware on the punch
     * route use, so all three agree about who has a punch surface.
     */
    private function punchState(User $user, CarbonInterface $today): ?PunchState
    {
        if (! $user->getAllPermissions()->pluck('name')->contains('ClockOwn:Mark')) {
            return null;
        }

        $todaysMarks = Mark::query()
            ->where('user_id', $user->id)
            ->whereDate('date_time', $today)
            ->get(['id', 'type']);

        return PunchState::fromTodaysMarks(
            $todaysMarks->contains(fn (Mark $mark): bool => $mark->type === MarkType::In),
            $todaysMarks->contains(fn (Mark $mark): bool => $mark->type === MarkType::Out),
        );
    }

    /**
     * Worked time from Monday to today, summed from the computed workdays. The
     * week's rows are at most seven, so they are added up in PHP rather than
     * with a dialect-specific `TIME_TO_SEC` aggregate.
     */
    private function weekWorkedHours(User $user, CarbonInterface $today): float
    {
        $seconds = Workday::query()
            ->where('user_id', $user->id)
            ->betweenDates($today->copy()->startOfWeek(CarbonInterface::MONDAY), $today)
            ->pluck('worked_time')
            ->sum(fn (?string $workedTime): int => $this->secondsOfDuration($workedTime));

        return $this->nonNegativeHours($seconds / 3600);
    }

    /**
     * An `HH:MM:SS` duration as whole seconds. Signed, because MySQL's TIMEDIFF
     * can produce a negative interval on a malformed day.
     */
    private function secondsOfDuration(?string $duration): int
    {
        if ($duration === null) {
            return 0;
        }

        $sign = str_starts_with($duration, '-') ? -1 : 1;
        [$hours, $minutes, $seconds] = array_pad(explode(':', ltrim($duration, '-')), 3, '0');

        return $sign * ((int) $hours * 3600 + (int) $minutes * 60 + (int) $seconds);
    }

    /**
     * Hours as the app will read them: never negative — the client rejects a
     * negative duration outright rather than render one — and rounded to the
     * two decimals the "32,5 / 44 hrs" line shows.
     */
    private function nonNegativeHours(?float $hours): float
    {
        return round(max(0.0, $hours ?? 0.0), 2);
    }
}
