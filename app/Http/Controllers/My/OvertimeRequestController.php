<?php

namespace App\Http\Controllers\My;

use App\Concerns\ResolvesTableSort;
use App\Enums\OvertimeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Notifications\OvertimeRequestSubmitted;
use App\Services\OrganizationSettings;
use App\Services\OvertimeRequestApprovers;
use App\Services\TimeZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service overtime requests (KOL-45, PRD §7.1 Mode A). Capability
 * is gated by permission middleware; every query is scoped to the
 * authenticated user. Only reachable when the tenant's overtime authorisation
 * mode allows requests — under pure post-hoc mode this whole flow is hidden.
 */
class OvertimeRequestController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request, OrganizationSettings $settings): Response
    {
        $this->assertModeAllowsRequests($settings);

        $user = $request->user();

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['date', 'created_at'],
            'date',
            'desc',
        );

        $status = $this->statusFilter($request);
        $from = $request->date('from');
        $to = $request->date('to');

        $requests = OvertimeRequest::query()
            ->where('user_id', $user->id)
            ->with('reviewedBy:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('my/overtime-requests/index', [
            'requests' => $requests->through(fn (OvertimeRequest $overtimeRequest) => [
                'id' => $overtimeRequest->id,
                'date' => $overtimeRequest->date->format('Y-m-d'),
                'requested_hours' => $overtimeRequest->requested_hours,
                'reason' => $overtimeRequest->reason,
                'status' => $overtimeRequest->status->value,
                'status_label' => $overtimeRequest->status->label(),
                'status_badge' => $overtimeRequest->status->badge(),
                'reviewed_by' => $overtimeRequest->reviewedBy?->name,
                'decision_reason' => $overtimeRequest->decision_reason,
                'created_at' => $overtimeRequest->created_at?->format('Y-m-d H:i'),
            ]),
            'filters' => [
                'status' => $status?->value,
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => OvertimeRequestStatus::options(),
        ]);
    }

    public function create(OrganizationSettings $settings): Response
    {
        $this->assertModeAllowsRequests($settings);

        return Inertia::render('my/overtime-requests/create', [
            'retroactiveWindowDays' => $settings->overtimeRetroactiveRequestDays(),
        ]);
    }

    public function store(Request $request, OrganizationSettings $settings, OvertimeRequestApprovers $approvers, TimeZoneService $timeZone): RedirectResponse
    {
        $this->assertModeAllowsRequests($settings);

        $user = $request->user();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'requested_hours' => ['required', 'date_format:H:i', 'after:00:00'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'requested_hours.after' => __('ui.overtime.requests.validation.positive_hours'),
        ]);

        // Parsed in the same timezone as `$timeZone->today()` so both sides of
        // the comparison below land on actual Chilean calendar days, not on
        // instants that happen to differ by the UTC/Santiago offset.
        $date = Carbon::parse($data['date'], $timeZone->getAppTimezone())->startOfDay();
        $windowDays = $settings->overtimeRetroactiveRequestDays();
        $earliestAllowed = $timeZone->today()->subDays($windowDays);

        if ($date->lessThan($earliestAllowed)) {
            throw ValidationException::withMessages([
                'date' => __('ui.overtime.requests.validation.retroactive_window', ['days' => $windowDays]),
            ]);
        }

        $overtimeRequest = OvertimeRequest::create([
            'user_id' => $user->id,
            'date' => $date,
            'requested_hours' => $data['requested_hours'].':00',
            'reason' => $data['reason'] ?? null,
            'status' => OvertimeRequestStatus::Pending,
        ]);

        Notification::send(
            $approvers->submissionRecipients($overtimeRequest),
            new OvertimeRequestSubmitted($overtimeRequest),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.requests.flash.created')]);

        return to_route('my.overtime-requests.index');
    }

    /**
     * Resolve the status tab into an OvertimeRequestStatus, treating "all" as
     * no filter.
     */
    private function statusFilter(Request $request): ?OvertimeRequestStatus
    {
        $value = $request->string('status')->trim()->value();

        return $value === '' ? null : OvertimeRequestStatus::tryFrom($value);
    }

    /**
     * The request flow only exists for tenants running pre-authorisation or
     * combined mode (PRD §7.1); under pure post-hoc it is hidden entirely
     * (KOL-45 AC #6).
     */
    private function assertModeAllowsRequests(OrganizationSettings $settings): void
    {
        abort_unless($settings->overtimeAuthorizationMode()->allowsRequests(), 404);
    }
}
