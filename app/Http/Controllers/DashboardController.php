<?php

namespace App\Http\Controllers;

use App\Actions\SyncOfficialHolidays;
use App\Enums\LeaveStatus;
use App\Enums\MarkType;
use App\Managers\MarkManager;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Scopes\HolidayScope;
use App\Models\User;
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
    private const UPCOMING_HOLIDAYS_LIMIT = 5;

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
        ]);
    }

    /**
     * Employees currently on approved leave, for the "Who's out today"
     * widget (KOL-119.3) — scoped exactly like LeavePolicy::viewTeam: null
     * (widget hidden) for anyone holding neither ViewTeam:Leave nor the
     * admin role (Leave has no dedicated admin permission, so admins reach
     * this the same way they reach the team leaves index — the super-admin
     * gate — rather than by holding the permission), org-wide for admins,
     * the supervisor's own direct reports otherwise.
     *
     * @return array<int, array{id: int, user: array{id: int, name: string, avatar: string|null}, type: string, type_label: string, return_date: string}>|null
     */
    private function whosOut(User $user): ?array
    {
        $isAdmin = $user->hasRole('admin');

        if (! $isAdmin && ! $user->can('ViewTeam:Leave')) {
            return null;
        }

        $supervisorId = $isAdmin ? null : $user->id;
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
