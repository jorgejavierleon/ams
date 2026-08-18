<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\OvertimeRequestStatus;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;
use App\Models\Workday;
use App\Notifications\OvertimeRequestApproved;
use App\Notifications\OvertimeRequestRejected;
use App\Services\OrganizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The standalone Mode A overtime-requests screen (KOL-72), extracted from the
 * old queue's Solicitudes tab. A request isn't tied to a computed
 * {@see Workday} until the day is worked and calculated, so it
 * doesn't belong on Jornadas (KOL-71) either — it gets its own screen under
 * the Horas extra hub instead.
 */
class OvertimeRequestController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request, OrganizationSettings $settings): Response
    {
        Gate::authorize('viewTeam', OvertimeRequest::class);

        $this->assertModeAllowsRequests($settings);

        $isAdmin = $request->user()->hasRole('admin');
        $supervisorId = $isAdmin ? null : $request->user()->id;
        $canDecide = $isAdmin || $request->user()->can('ApproveTeam:OvertimeAuthorization');

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['date'],
            'date',
            'desc',
        );

        $status = $this->statusFilter($request);

        $requests = OvertimeRequest::query()
            ->with(['user:id,name,supervisor_id', 'reviewedBy:id,name'])
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('supervisor_id', $supervisorId),
            ))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('overtime/requests/index', [
            'requests' => $requests->through(fn (OvertimeRequest $overtimeRequest): array => [
                'id' => $overtimeRequest->id,
                'employee' => $overtimeRequest->user?->name,
                'date' => $overtimeRequest->date->format('Y-m-d'),
                'requested_hours' => $overtimeRequest->requested_hours,
                'reason' => $overtimeRequest->reason,
                'status' => $overtimeRequest->status->value,
                'status_label' => $overtimeRequest->status->label(),
                'status_badge' => $overtimeRequest->status->badge(),
                'reviewed_by' => $overtimeRequest->reviewedBy?->name,
            ]),
            'filters' => [
                'status' => $status?->value,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => OvertimeRequestStatus::options(),
            'can' => [
                'decide' => $canDecide,
            ],
        ]);
    }

    /**
     * Approve an employee's overtime request. A green light to work the
     * hours, never itself a payable one — the eventual worked day still goes
     * through {@see OvertimeAuthorization} once calculated.
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        Gate::authorize('approve', $overtimeRequest);

        abort_if(! $overtimeRequest->isPending(), 403);

        $overtimeRequest->approve($request->user());

        $overtimeRequest->user->notify(new OvertimeRequestApproved($overtimeRequest));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.requests.review.flash.approved')]);

        return back();
    }

    /**
     * Reject an employee's overtime request. Does not stop them from working
     * the day — it only means the hours, if worked, arrive at the
     * authorisation review without a prior request behind them.
     */
    public function reject(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        Gate::authorize('reject', $overtimeRequest);

        abort_if(! $overtimeRequest->isPending(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $overtimeRequest->reject($request->user(), $data['reason']);

        $overtimeRequest->user->notify(new OvertimeRequestRejected($overtimeRequest));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.requests.review.flash.rejected')]);

        return back();
    }

    /**
     * Resolve the status tab into a status, defaulting to `pending` — this is
     * the pending-requests screen first and a decision history second. `all`
     * (explicit or via the tab) clears the filter.
     */
    private function statusFilter(Request $request): ?OvertimeRequestStatus
    {
        if (! $request->has('status')) {
            return OvertimeRequestStatus::Pending;
        }

        $value = $request->string('status')->trim()->value();

        return $value === '' || $value === 'all' ? null : OvertimeRequestStatus::tryFrom($value);
    }

    /**
     * Only reachable when the tenant runs pre-authorisation or combined mode
     * (PRD §7.1); under pure post-hoc there is nothing to review here.
     */
    private function assertModeAllowsRequests(OrganizationSettings $settings): void
    {
        abort_unless($settings->overtimeAuthorizationMode()->allowsRequests(), 404);
    }
}
