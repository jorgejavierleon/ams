<?php

namespace App\Http\Controllers\My;

use App\Concerns\ResolvesTableSort;
use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Managers\LeaveManager;
use App\Models\Leave;
use App\Services\BusinessDaysCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Employee self-service leaves: an employee requests and views their own leaves.
 * Capability is gated by permission middleware; every query is scoped to the
 * authenticated user.
 */
class LeaveController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $user = $request->user();

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['start_date', 'end_date', 'business_days_requested', 'created_at'],
            'start_date',
            'desc',
        );

        $status = $this->statusFilter($request);
        $from = $request->date('from');
        $to = $request->date('to');

        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->with('approver:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            // Overlap match: the leave touches the requested [from, to] window.
            ->when($from, fn ($query) => $query->whereDate('end_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('start_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('my/leaves/index', [
            'leaves' => $leaves->through(fn (Leave $leave) => [
                'id' => $leave->id,
                'type' => $leave->type->value,
                'type_label' => $leave->type->label(),
                'start_date' => $leave->start_date->format('Y-m-d'),
                'end_date' => $leave->end_date->format('Y-m-d'),
                'half_day' => $leave->half_day,
                'half_day_type' => $leave->half_day_type?->value,
                'business_days_requested' => $leave->business_days_requested,
                'status' => $leave->status->value,
                'status_label' => $leave->status->label(),
                'approved_by' => $leave->approver?->name,
                'is_medical' => $leave->type === LeaveType::Medical,
                'medical_leave_number' => $leave->medical_leave_number,
                'medical_leave_doctor' => $leave->medical_leave_doctor,
                'notes' => $leave->notes,
                'created_at' => $leave->created_at?->format('Y-m-d H:i'),
            ]),
            'filters' => [
                'status' => $status?->value,
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => LeaveStatus::options(),
            'vacationBalance' => $this->vacationBalance($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('my/leaves/create', [
            'typeOptions' => LeaveType::selfServiceOptions(),
            'halfDayTypeOptions' => LeaveHalfDayType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)->except([LeaveType::Medical])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['boolean'],
            'half_day_type' => ['nullable', 'required_if:half_day,true', Rule::enum(LeaveHalfDayType::class)],
            'business_days_requested' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // A half-day leave is confined to a single day and always counts 0.5.
        if ($request->boolean('half_day')) {
            if ($data['start_date'] !== $data['end_date']) {
                throw ValidationException::withMessages([
                    'end_date' => __('ui.leaves.validation.half_day_single_day'),
                ]);
            }

            $data['business_days_requested'] = 0.5;
        } else {
            $data['half_day'] = false;
            $data['half_day_type'] = null;
        }

        Leave::create([
            ...$data,
            // The requester is always the authenticated employee.
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'company_id' => $user->company_id,
            'status' => LeaveStatus::Pending,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.leaves.flash.created')]);

        return to_route('my.leaves.index');
    }

    /**
     * Estimate the business days a leave would span for the authenticated
     * employee, to pre-fill the request form.
     */
    public function businessDays(Request $request, BusinessDaysCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        $businessDays = $calculator->calculate(
            $request->user(),
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
        );

        return response()->json(['business_days' => $businessDays]);
    }

    /**
     * Cancel (delete) one of the employee's own leaves while it is still pending.
     *
     * @throws Throwable
     */
    public function destroy(Request $request, Leave $leave, LeaveManager $manager): RedirectResponse
    {
        abort_unless(
            $leave->user_id === $request->user()->id && $leave->status === LeaveStatus::Pending,
            403,
        );

        $manager->delete($leave);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.leaves.flash.deleted')]);

        return back();
    }

    /**
     * Resolve the status tab into a LeaveStatus, treating "all" as no filter.
     */
    private function statusFilter(Request $request): ?LeaveStatus
    {
        $value = $request->string('status')->trim()->value();

        return $value === '' ? null : LeaveStatus::tryFrom($value);
    }

    /**
     * The authenticated employee's vacation balance summary.
     *
     * @return array{used: float, available: float, total: float}
     */
    private function vacationBalance(Request $request): array
    {
        $user = $request->user();

        $used = (float) Leave::query()
            ->where('user_id', $user->id)
            ->where('type', LeaveType::Vacation)
            ->where('status', LeaveStatus::Approved)
            ->sum('business_days_requested');

        $available = (float) $user->vacation_days + (float) $user->additional_vacation_days;

        return [
            'used' => $used,
            'available' => $available,
            'total' => $used + $available,
        ];
    }
}
