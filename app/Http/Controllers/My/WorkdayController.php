<?php

namespace App\Http\Controllers\My;

use App\Enums\MarkModificationStatus;
use App\Http\Controllers\Controller;
use App\Managers\MarkModificationManager;
use App\Models\MarkModification;
use App\Models\Workday;
use App\Services\WorkdayPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service workdays: the authenticated employee reviews their own
 * computed attendance and acts on the mark-correction requests an admin has
 * opened against their days. Every query is scoped to the authenticated user;
 * capability is gated by permission middleware on the routes.
 */
class WorkdayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // The list is date-first: default to the current month, the natural unit
        // an employee reviews their attendance in.
        $from = $request->date('from') ?? Carbon::today()->startOfMonth();
        $to = $request->date('to') ?? Carbon::today()->endOfMonth();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        $workdays = Workday::query()
            ->where('user_id', $user->id)
            ->with(['shift:id,name', 'leave:id,type'])
            ->withCount('pendingMarkModifications')
            ->betweenDates($from, $to)
            ->orderByDesc('date')
            ->get();

        // Pending corrections are the priority action and are time-sensitive, so
        // they are surfaced across every workday rather than limited to the
        // selected date range.
        $pending = MarkModification::query()
            ->where('user_id', $user->id)
            ->where('status', MarkModificationStatus::Pending)
            ->with(['mark', 'workday:id,date', 'createdBy:id,name'])
            ->latest('created_at')
            ->get();

        return Inertia::render('my/workdays/index', [
            'workdays' => $workdays->map(fn (Workday $workday) => [
                'id' => $workday->id,
                'date' => $workday->date->format('Y-m-d'),
                'date_label' => $workday->date->isoFormat('ddd D [de] MMM'),
                'weekday' => $workday->date->isoFormat('dddd'),
                'status' => $workday->status?->value,
                'status_label' => $workday->status?->label(),
                'status_badge' => $workday->status?->badge(),
                'mark_in_at' => $workday->mark_in_at?->format('H:i'),
                'mark_out_at' => $workday->mark_out_at?->format('H:i'),
                'worked_time' => $this->trimSeconds($workday->worked_time),
                'shift' => $workday->shift?->name,
                'leave_type' => $workday->leave?->type->label(),
                'pending_modifications' => $workday->pending_mark_modifications_count,
            ])->all(),
            'pendingModifications' => $pending
                ->map(fn (MarkModification $modification) => $this->presentModification($modification))
                ->all(),
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * The read-only detail of one of the employee's own workdays: the same KPIs,
     * attendance strip and mark-modification timeline the admin sees, minus any
     * ability to request mark changes. The employee can still approve or decline
     * the corrections an admin has opened against the day.
     */
    public function show(Request $request, Workday $workday, WorkdayPresenter $presenter): Response
    {
        abort_unless($workday->user_id === $request->user()->id, 403);

        $workday->load([
            'user:id,name',
            'shift:id,name',
            'premise:id,name',
            'leave:id,type,start_date,end_date',
            'markIn',
            'markOut',
            'markModifications' => fn ($query) => $query->latest('created_at'),
            'markModifications.mark',
            'markModifications.createdBy:id,name',
            'markModifications.reviewedBy:id,name',
        ]);

        return Inertia::render('my/workdays/show', [
            'workday' => $presenter->workday($workday),
            'modifications' => $presenter->modifications($workday),
        ]);
    }

    /**
     * Approve a correction an admin requested against one of the employee's own
     * marks. The manager rewrites the mark and recalculates the day; only the
     * owning employee may act, and only while the request is still actionable.
     */
    public function approveModification(Request $request, Workday $workday, MarkModification $markModification, MarkModificationManager $manager): RedirectResponse
    {
        $this->authorizeReview($request, $workday, $markModification);

        $manager->approve($markModification);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.workdays.my.flash.approved'),
        ]);

        return back();
    }

    /**
     * Decline a correction an admin requested against one of the employee's own
     * marks. The request is closed without touching the underlying mark; only the
     * owning employee may act, and only while the request is still actionable.
     */
    public function declineModification(Request $request, Workday $workday, MarkModification $markModification, MarkModificationManager $manager): RedirectResponse
    {
        $this->authorizeReview($request, $workday, $markModification);

        $manager->decline($markModification);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.workdays.my.flash.declined'),
        ]);

        return back();
    }

    /**
     * Guard an inline review action: the workday and its modification must both
     * belong to the authenticated employee, and the request must still be
     * actionable (pending and within its review window).
     */
    private function authorizeReview(Request $request, Workday $workday, MarkModification $modification): void
    {
        abort_unless(
            $workday->user_id === $request->user()->id
                && $modification->user_id === $request->user()->id
                && $modification->isActionable(),
            403,
        );
    }

    /**
     * Shape a pending correction for the review card: the proposed change against
     * the current mark, why it was requested and who opened it.
     *
     * @return array<string, mixed>
     */
    private function presentModification(MarkModification $modification): array
    {
        return [
            'id' => $modification->id,
            'workday_id' => $modification->workday_id,
            'date_label' => $modification->workday?->date->isoFormat('dddd D [de] MMMM'),
            'mark_type' => $modification->mark_type?->value,
            'mark_type_label' => $modification->mark_type?->label(),
            'original_time' => ($modification->original_date_time ?? $modification->mark?->date_time)?->format('H:i'),
            'proposed_time' => $modification->date_time->format('H:i'),
            'reason' => $modification->reason?->label(),
            'notes' => $modification->notes,
            'requested_by' => $modification->createdBy?->name,
            'created_ago' => $modification->created_at?->diffForHumans(),
            'is_expired' => $modification->isExpired(),
        ];
    }

    /**
     * Drop the seconds from a stored HH:MM:SS time for compact display.
     */
    private function trimSeconds(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i');
    }
}
