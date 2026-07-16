<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeaveCalendarController extends Controller
{
    /**
     * Render the full-page leaves calendar. The events themselves are loaded
     * asynchronously per visible range from {@see events()}.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewTeam', Leave::class);

        return Inertia::render('leaves/calendar', [
            'leaveTypes' => LeaveType::legendOptions(),
        ]);
    }

    /**
     * Return approved leaves overlapping the requested [start, end] window as
     * FullCalendar event objects. Admins see every request; supervisors are
     * scoped to their own team. Organization scoping is enforced globally by
     * the model's OrganizationScope.
     */
    public function events(Request $request): JsonResponse
    {
        Gate::authorize('viewTeam', Leave::class);

        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        // Admins manage every request; supervisors are scoped to their team.
        $supervisorId = $request->user()->hasRole('admin') ? null : $request->user()->id;

        $events = Leave::query()
            ->with(['user:id,name', 'approver:id,name'])
            ->where('status', LeaveStatus::Approved)
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('supervisor_id', $supervisorId),
            ))
            // Overlap match: the leave touches the requested [start, end] window.
            ->whereDate('end_date', '>=', $data['start'])
            ->whereDate('start_date', '<=', $data['end'])
            ->get()
            ->map(fn (Leave $leave): array => [
                'id' => (string) $leave->id,
                'title' => $leave->user?->name,
                'start' => $leave->start_date->toDateString(),
                // FullCalendar treats the end of an all-day event as exclusive.
                'end' => $leave->end_date->copy()->addDay()->toDateString(),
                'allDay' => true,
                'color' => $leave->type->color(),
                'extendedProps' => [
                    'type' => $leave->type->value,
                    'type_label' => $leave->type->label(),
                    'status' => $leave->status->value,
                    'status_label' => $leave->status->label(),
                    'employee' => $leave->user?->name,
                    'approved_by' => $leave->approver?->name,
                    'start_date' => $leave->start_date->toDateString(),
                    'end_date' => $leave->end_date->toDateString(),
                ],
            ])
            ->all();

        return response()->json($events);
    }
}
