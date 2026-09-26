<?php

namespace App\Http\Controllers;

use App\Actions\SyncOfficialHolidays;
use App\Enums\LeaveStatus;
use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Managers\MarkManager;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Scopes\HolidayScope;
use App\Models\User;
use App\Models\Workday;
use App\Services\WorkdayCalculator;
use App\Support\Duration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The authenticated landing page. For employees who may clock in/out it surfaces
 * the attendance widget state (today's shift and any punches already made) so
 * registering entry/exit is the first thing they see after logging in.
 */
class DashboardController extends Controller
{
    /**
     * How many upcoming holidays the dashboard widget shows — a glance, not
     * the full calendar (KOL-119.4).
     */
    private const UPCOMING_HOLIDAYS_LIMIT = 3;

    /**
     * The attendance overview chart (KOL-121) trends the last 4 calendar
     * weeks, inclusive of today.
     */
    private const ATTENDANCE_OVERVIEW_DAYS = 28;

    public function index(Request $request, MarkManager $marks): Response
    {
        $user = $request->user();

        // Gate on the permission the user actually holds — not the super-admin
        // gate — so the widget matches the `permission:` middleware guarding the
        // store route (which the admin gate does not bypass). Admins hold the
        // permission directly via the seeder, so they get the widget too.
        $canClock = $user->getAllPermissions()->pluck('name')->contains('ClockOwn:Mark');

        return Inertia::render('dashboard', [
            'clock' => $canClock
                ? [
                    'shift' => $marks->getShiftForToday($user),
                    'in' => $marks->getTodayMark(MarkType::In, $user)?->date_time->format('H:i'),
                    'out' => $marks->getTodayMark(MarkType::Out, $user)?->date_time->format('H:i'),
                ]
                : null,
            'whosOut' => $this->whosOut($user),
            'upcomingHolidays' => $this->upcomingHolidays(),
            'attendanceRate' => $this->attendanceRate($user),
            'attendanceOverview' => $this->attendanceOverview($user),
        ]);
    }

    /**
     * Employees currently on approved leave, for the "Who's out today"
     * widget (KOL-119.3) — scoped exactly like LeavePolicy::viewTeam: null
     * (widget hidden) for anyone holding neither ViewTeam:Leave nor the
     * admin role nor the Owner bypass (KOL-133), org-wide for admins and the
     * Owner, the supervisor's own direct reports otherwise.
     *
     * @return array<int, array{id: int, user: array{id: int, name: string, avatar: string|null}, type: string, type_label: string, return_date: string}>|null
     */
    private function whosOut(User $user): ?array
    {
        $isOrgWide = $user->hasRole('admin') || $user->isOwner();

        if (! $isOrgWide && ! $user->can('ViewTeam:Leave')) {
            return null;
        }

        $supervisorId = $isOrgWide ? null : $user->id;
        $today = Carbon::today();

        return Leave::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->with('user:id,name')
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($employee) => $employee->where('supervisor_id', $supervisorId),
            ))
            ->get()
            ->map(fn (Leave $leave) => [
                'id' => $leave->id,
                'user' => [
                    'id' => $leave->user->id,
                    'name' => $leave->user->name,
                    'avatar' => $leave->user->avatar,
                ],
                'type' => $leave->type->value,
                'type_label' => $leave->type->label(),
                // The day they're back at work, not the last day of leave.
                'return_date' => $leave->end_date->copy()->addDay()->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    /**
     * The team's attendance rate for the current week vs last week (KOL-120),
     * scoped exactly like {@see whosOut()}: null (card hidden) for anyone
     * holding neither ViewTeam:Workday nor the admin role nor the Owner
     * bypass (KOL-133), org-wide for admins and the Owner, the supervisor's
     * own direct reports otherwise. Attendance is already computed per
     * employee per day by {@see WorkdayCalculator} (any {@see WorkdayStatus}
     * other than Absent counts as attended), so this only aggregates the
     * existing Workday rows rather than computing new attendance logic.
     *
     * @return array{rate: float|null, trend: float|null}|null
     */
    private function attendanceRate(User $user): ?array
    {
        $isOrgWide = $user->hasRole('admin') || $user->isOwner();

        if (! $isOrgWide && ! $user->can('ViewTeam:Workday')) {
            return null;
        }

        $supervisorId = $isOrgWide ? null : $user->id;
        $today = Carbon::today();
        $currentWeekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $previousWeekStart = $currentWeekStart->copy()->subWeek();
        $previousWeekEnd = $currentWeekStart->copy()->subDay();

        $currentRate = $this->weeklyAttendanceRate($supervisorId, $currentWeekStart, $today->copy()->endOfWeek(Carbon::SUNDAY));
        $previousRate = $this->weeklyAttendanceRate($supervisorId, $previousWeekStart, $previousWeekEnd);

        return [
            'rate' => $currentRate,
            'trend' => $currentRate !== null && $previousRate !== null
                ? round($currentRate - $previousRate, 1)
                : null,
        ];
    }

    /**
     * The attendance rate (attended / scheduled workdays, as a percentage)
     * for one week, or null when the visible scope has no computed Workday
     * rows in the period — the empty state, never a misleading 0%.
     */
    private function weeklyAttendanceRate(?int $supervisorId, Carbon $from, Carbon $to): ?float
    {
        $query = Workday::query()
            ->betweenDates($from, $to)
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($employee) => $employee->where('supervisor_id', $supervisorId),
            ));

        $total = (clone $query)->count();

        if ($total === 0) {
            return null;
        }

        $absent = (clone $query)->where('status', WorkdayStatus::Absent)->count();

        return round((($total - $absent) / $total) * 100, 1);
    }

    /**
     * Daily on-time/late/absent counts for the attendance overview chart
     * (KOL-121), scoped exactly like {@see attendanceRate()}: null (chart
     * hidden) for anyone holding neither ViewTeam:Workday nor the admin
     * role nor the Owner bypass (KOL-133), org-wide for admins and the
     * Owner, the supervisor's own direct reports otherwise. `days` is empty
     * when the visible scope has no computed Workday rows anywhere in the
     * period — the empty state — and otherwise always holds one entry per
     * day of the period, zero-filled for days with no rows, so the chart's
     * x-axis stays continuous.
     *
     * @return array{days: array<int, array{date: string, on_time: int, late: int, absent: int}>}|null
     */
    private function attendanceOverview(User $user): ?array
    {
        $isOrgWide = $user->hasRole('admin') || $user->isOwner();

        if (! $isOrgWide && ! $user->can('ViewTeam:Workday')) {
            return null;
        }

        $supervisorId = $isOrgWide ? null : $user->id;
        $today = Carbon::today();
        $periodStart = $today->copy()->subDays(self::ATTENDANCE_OVERVIEW_DAYS - 1);

        $workdays = Workday::query()
            ->betweenDates($periodStart, $today)
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($employee) => $employee->where('supervisor_id', $supervisorId),
            ))
            ->get(['date', 'status', 'in_time_difference']);

        if ($workdays->isEmpty()) {
            return ['days' => []];
        }

        $workdaysByDate = $workdays->groupBy(fn (Workday $workday) => $workday->date->toDateString());

        $days = [];

        for ($date = $periodStart->copy(); $date->lte($today); $date->addDay()) {
            $dateKey = $date->toDateString();
            $counts = ['on_time' => 0, 'late' => 0, 'absent' => 0];

            foreach ($workdaysByDate->get($dateKey, []) as $workday) {
                $counts[$this->attendanceBucket($workday)]++;
            }

            $days[] = ['date' => $dateKey, ...$counts];
        }

        return ['days' => $days];
    }

    /**
     * Which of the three attendance overview buckets a Workday row falls
     * into: absent when its status says so, otherwise late when its
     * clock-in ran past the shift start ({@see Workday::$in_time_difference}
     * is positive), otherwise on-time.
     *
     * @return 'on_time'|'late'|'absent'
     */
    private function attendanceBucket(Workday $workday): string
    {
        if ($workday->status === WorkdayStatus::Absent) {
            return 'absent';
        }

        $inTimeDifference = $workday->in_time_difference;
        $isLate = $inTimeDifference !== null
            && ! str_starts_with($inTimeDifference, '-')
            && Duration::fromTimeString($inTimeDifference)->seconds > 0;

        return $isLate ? 'late' : 'on_time';
    }

    /**
     * The next few upcoming holidays for the dashboard widget (KOL-119.4),
     * visible to every authenticated user with no permission gate.
     * {@see HolidayScope} already limits reads to the
     * official list plus the current organization's own holidays; `country`
     * is filtered explicitly on top since the app is Chile-only today
     * (mirrors the 'cl' literal in {@see SyncOfficialHolidays}).
     *
     * @return array<int, array{id: int, name: string, date: string}>
     */
    private function upcomingHolidays(): array
    {
        return Holiday::query()
            ->where('country', 'cl')
            ->whereDate('date', '>=', Carbon::today())
            ->orderBy('date')
            ->limit(self::UPCOMING_HOLIDAYS_LIMIT)
            ->get()
            ->map(fn (Holiday $holiday) => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date->format('Y-m-d'),
            ])
            ->all();
    }
}
