<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\OvertimeAuthorizationStatus;
use App\Enums\OvertimeRequestStatus;
use App\Exceptions\OvertimeDecisionRefused;
use App\Models\Company;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\OvertimeRequestApproved;
use App\Notifications\OvertimeRequestRejected;
use App\Services\OrganizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The pending-overtime queue (KOL-44, PRD §7.5): the screen where a supervisor
 * or HR admin approves or objects to a day's overtime, individually or in
 * bulk. Nothing here bypasses {@see OvertimeAuthorization::approve()} /
 * {@see OvertimeAuthorization::object()} — the anomaly block (KOL-40) and the
 * legal-cap justification requirement (KOL-41) are enforced on the model's
 * write path, so a bulk decision hits them exactly as an individual one does.
 */
class OvertimeQueueController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request, OrganizationSettings $settings): Response
    {
        Gate::authorize('viewTeam', OvertimeAuthorization::class);

        $isAdmin = $request->user()->hasRole('admin');
        $supervisorId = $isAdmin ? null : $request->user()->id;
        $canDecide = $isAdmin || $request->user()->can('ApproveTeam:OvertimeAuthorization');
        $showRequests = $settings->overtimeAuthorizationMode()->allowsRequests();

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['date', 'calculated_hours', 'status'],
            'date',
            'desc',
        );

        $status = $this->statusFilter($request);
        $employeeIds = $this->idListFilter($request, 'employees');
        $from = $request->date('from');
        $to = $request->date('to');
        $requestStatus = $this->requestStatusFilter($request);

        $authorizations = OvertimeAuthorization::query()
            ->with(['user:id,name,supervisor_id', 'workday:id,anomaly_flags', 'reviewedBy:id,name'])
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('supervisor_id', $supervisorId),
            ))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($employeeIds, fn ($query) => $query->whereIn('user_id', $employeeIds))
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        $requestsQuery = OvertimeRequest::query()
            ->with(['user:id,name,supervisor_id', 'reviewedBy:id,name'])
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('supervisor_id', $supervisorId),
            ));

        // Always known regardless of the history filter below, so the tab
        // badge keeps showing "how many need a decision", not "how many rows
        // match the current filter".
        $pendingRequestsCount = $showRequests ? (clone $requestsQuery)->pending()->count() : 0;

        $requests = $showRequests
            ? $requestsQuery
                ->when($requestStatus, fn ($query) => $query->where('status', $requestStatus))
                ->orderBy('date', 'desc')
                ->paginate(20, pageName: 'requests_page')
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 20, 1, ['pageName' => 'requests_page']);

        return Inertia::render('overtime/queue/index', [
            'authorizations' => $authorizations->through(fn (OvertimeAuthorization $authorization) => [
                'id' => $authorization->id,
                'employee' => $authorization->user?->name,
                'date' => $authorization->date->format('Y-m-d'),
                'calculated_hours' => $authorization->calculated_hours,
                'requested_hours' => $authorization->requested_hours,
                'authorized_hours' => $authorization->authorized_hours,
                'final_hours' => $authorization->final_hours,
                'status' => $authorization->status->value,
                'status_label' => $authorization->status->label(),
                'status_badge' => $authorization->status->badge(),
                'reason' => $authorization->reason,
                'reviewed_by' => $authorization->reviewedBy?->name,
                'reviewed_at' => $authorization->reviewed_at?->format('Y-m-d H:i'),
                'is_flagged' => $authorization->workday?->isFlagged() ?? false,
                'anomaly_reasons' => collect($authorization->workday?->anomalyFlags() ?? [])
                    ->map(fn ($reason) => $reason->label())
                    ->all(),
            ]),
            'filters' => [
                'status' => $status?->value,
                'employees' => array_map('strval', $employeeIds),
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
                'sort' => $sort,
                'direction' => $direction,
                'request_status' => $requestStatus?->value,
            ],
            'employeeOptions' => $this->employeeOptions($supervisorId),
            'statusOptions' => OvertimeAuthorizationStatus::options(),
            'requestStatusOptions' => OvertimeRequestStatus::options(),
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
            'pendingRequestsCount' => $pendingRequestsCount,
            'can' => [
                'decide' => $canDecide,
                'requests' => $showRequests,
            ],
        ]);
    }

    /**
     * Approve a single day. The authorised figure defaults to the full
     * calculated one but the reviewer may authorise fewer hours (PRD §7.5).
     * A flagged day is refused before it ever reaches the model, so the
     * response names the flag reason rather than a generic error (KOL-40).
     */
    public function approve(Request $request, OvertimeAuthorization $overtimeAuthorization): RedirectResponse
    {
        Gate::authorize('approve', $overtimeAuthorization);

        abort_if(! $overtimeAuthorization->isPending(), 403);

        $data = $request->validate([
            'authorized_hours' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($overtimeAuthorization->workday?->isFlagged()) {
            throw ValidationException::withMessages([
                'reason' => __('ui.overtime.queue.errors.unresolved_anomalies', [
                    'reasons' => collect($overtimeAuthorization->workday->anomalyFlags())
                        ->map(fn ($reason) => $reason->label())
                        ->implode(', '),
                ]),
            ]);
        }

        try {
            $overtimeAuthorization->approve(
                $request->user(),
                isset($data['authorized_hours']) ? $data['authorized_hours'].':00' : null,
                $data['reason'] ?? null,
            );
        } catch (OvertimeDecisionRefused) {
            throw ValidationException::withMessages([
                'reason' => __('ui.overtime.queue.errors.reason_required'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.queue.flash.approved')]);

        return back();
    }

    /**
     * Objecting is always reachable, even for a flagged day (PRD §7.4) — a
     * refusal never needs the same trust a payment does. The raw marks are
     * never touched; only this record's status changes.
     */
    public function object(Request $request, OvertimeAuthorization $overtimeAuthorization): RedirectResponse
    {
        Gate::authorize('object', $overtimeAuthorization);

        abort_if(! $overtimeAuthorization->isPending(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $overtimeAuthorization->object($request->user(), $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.queue.flash.objected')]);

        return back();
    }

    /**
     * Approve an employee's overtime request (KOL-45, Mode A). A green light
     * to work the hours, never itself a payable one — the eventual worked day
     * still goes through {@see OvertimeAuthorization} once calculated.
     */
    public function approveRequest(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        Gate::authorize('approve', $overtimeRequest);

        abort_if(! $overtimeRequest->isPending(), 403);

        $overtimeRequest->approve($request->user());

        $overtimeRequest->user->notify(new OvertimeRequestApproved($overtimeRequest));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.queue.requests.flash.approved')]);

        return back();
    }

    /**
     * Reject an employee's overtime request. Does not stop them from working
     * the day — it only means the hours, if worked, arrive at this queue
     * without a prior request behind them (KOL-45 AC #5).
     */
    public function rejectRequest(Request $request, OvertimeRequest $overtimeRequest): RedirectResponse
    {
        Gate::authorize('reject', $overtimeRequest);

        abort_if(! $overtimeRequest->isPending(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $overtimeRequest->reject($request->user(), $data['reason']);

        $overtimeRequest->user->notify(new OvertimeRequestRejected($overtimeRequest));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.queue.requests.flash.rejected')]);

        return back();
    }

    /**
     * Decide a selection at once. Each row is authorised in full (no per-row
     * editable figure in bulk — that stays an individual-decision feature) and
     * goes through the very same {@see OvertimeAuthorization::approve()} /
     * {@see OvertimeAuthorization::object()} calls as the single-record
     * actions, so a flagged day or an unjustified cap breach inside the
     * selection is simply left pending rather than waved through. The count
     * reported is only what actually changed.
     */
    public function bulkDecide(Request $request): RedirectResponse
    {
        $organizationId = Company::currentOrganizationId();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                Rule::exists('overtime_authorizations', 'id')->where('organization_id', $organizationId),
            ],
            'action' => ['required', Rule::in(['approve', 'object'])],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:action,object'],
        ]);

        $authorizations = OvertimeAuthorization::query()
            ->whereIn('id', $data['ids'])
            ->pending()
            ->with(['user', 'workday'])
            ->get();

        $user = $request->user();
        $decided = 0;

        DB::transaction(function () use ($authorizations, $data, $user, &$decided): void {
            foreach ($authorizations as $authorization) {
                $ability = $data['action'] === 'approve' ? 'approve' : 'object';

                if (Gate::denies($ability, $authorization)) {
                    continue;
                }

                try {
                    if ($data['action'] === 'approve') {
                        $authorization->approve($user, reason: $data['reason'] ?? null);
                    } else {
                        $authorization->object($user, $data['reason']);
                    }

                    $decided++;
                } catch (OvertimeDecisionRefused) {
                    // Left pending: a flagged day or an unjustified cap breach
                    // in the selection is not silently approved because the
                    // rest of the batch was fine.
                }
            }
        });

        Inertia::flash('toast', [
            'type' => $decided > 0 ? 'success' : 'error',
            'message' => __('ui.overtime.queue.flash.bulk_decided', [
                'decided' => $decided,
                'total' => $authorizations->count(),
            ]),
        ]);

        return back();
    }

    /**
     * Resolve the status tab into a status, defaulting to `pending` — this is
     * the pending-overtime queue first and an audit trail second. `all`
     * (explicit or via the tab) clears the filter.
     */
    private function statusFilter(Request $request): ?OvertimeAuthorizationStatus
    {
        if (! $request->has('status')) {
            return OvertimeAuthorizationStatus::Pending;
        }

        $value = $request->string('status')->trim()->value();

        return $value === '' || $value === 'all' ? null : OvertimeAuthorizationStatus::tryFrom($value);
    }

    /**
     * Resolve the Solicitudes tab's own status filter, independent of
     * {@see statusFilter()} above — defaults to `pending` for the same
     * reason, `all` clears it so a decided request stays reachable.
     */
    private function requestStatusFilter(Request $request): ?OvertimeRequestStatus
    {
        if (! $request->has('request_status')) {
            return OvertimeRequestStatus::Pending;
        }

        $value = $request->string('request_status')->trim()->value();

        return $value === '' || $value === 'all' ? null : OvertimeRequestStatus::tryFrom($value);
    }

    /**
     * Resolve a repeated id filter (e.g. `employees[]=1&employees[]=2`).
     *
     * @return array<int, int>
     */
    private function idListFilter(Request $request, string $key): array
    {
        return collect((array) $request->input($key, []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Employees of the current organization for the filter's select. Scoped
     * to a supervisor's own team when one is given.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function employeeOptions(?int $supervisorId): array
    {
        return User::query()
            ->employees()
            ->where('organization_id', Company::currentOrganizationId())
            ->when($supervisorId, fn ($query) => $query->where('supervisor_id', $supervisorId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $employee): array => ['value' => (string) $employee->id, 'label' => $employee->name])
            ->all();
    }
}
