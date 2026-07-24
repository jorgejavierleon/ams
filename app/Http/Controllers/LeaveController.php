<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Managers\LeaveManager;
use App\Models\Company;
use App\Models\Leave;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\BusinessDaysCalculator;
use App\Services\LeaveApprovers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LeaveController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        Gate::authorize('viewTeam', Leave::class);

        // Admins manage every request; supervisors are scoped to their own team.
        $isAdmin = $request->user()->hasRole('admin');
        $supervisorId = $isAdmin ? null : $request->user()->id;

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['start_date', 'end_date', 'business_days_requested', 'created_at'],
            'start_date',
            'desc',
        );

        $status = $this->statusFilter($request);
        $employeeIds = $this->idListFilter($request, 'employees');
        $from = $request->date('from');
        $to = $request->date('to');

        $leaves = Leave::query()
            ->with(['user:id,name', 'approver:id,name'])
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('supervisor_id', $supervisorId),
            ))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($employeeIds, fn ($query) => $query->whereIn('user_id', $employeeIds))
            // Overlap match: the leave touches the requested [from, to] window.
            ->when($from, fn ($query) => $query->whereDate('end_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('start_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('leaves/index', [
            'leaves' => $leaves->through(fn (Leave $leave) => [
                'id' => $leave->id,
                'employee' => $leave->user?->name,
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
                'employees' => array_map('strval', $employeeIds),
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'employeeOptions' => $this->employeeOptions($supervisorId),
            'statusOptions' => LeaveStatus::options(),
            // Creating-for-others and deleting stay admin-only; approve/reject is
            // available to admins and to supervisors holding ApproveTeam:Leave
            // (the list is already scoped to the supervisor's own team).
            'can' => [
                'create' => $isAdmin,
                'delete' => $isAdmin,
                'approve' => $isAdmin || $request->user()->can('ApproveTeam:Leave'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('leaves/create', [
            'employeeOptions' => $this->employeeOptions(),
            'typeOptions' => LeaveType::options(),
            'halfDayTypeOptions' => LeaveHalfDayType::options(),
        ]);
    }

    public function store(Request $request, LeaveApprovers $approvers): RedirectResponse
    {
        $organizationId = Company::currentOrganizationId();

        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['boolean'],
            'half_day_type' => ['nullable', 'required_if:half_day,true', Rule::enum(LeaveHalfDayType::class)],
            'business_days_requested' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'medical_leave_number' => ['nullable', 'string', 'max:255'],
            'medical_leave_doctor' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = User::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($request->integer('user_id'));

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

        $leave = Leave::create([
            ...$data,
            'organization_id' => $organizationId,
            'company_id' => $employee->company_id,
            // New requests always start pending; medical leaves are
            // auto-approved by the observer.
            'status' => LeaveStatus::Pending,
        ]);

        // Notify approvers only for requests that actually need a decision;
        // medical leaves are auto-approved on creation.
        if ($leave->status === LeaveStatus::Pending) {
            Notification::send(
                $approvers->submissionRecipients($leave),
                new LeaveRequestSubmitted($leave),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.leaves.flash.created')]);

        return to_route('leaves.index');
    }

    /**
     * Estimate the business days a leave would span, from the employee's shift
     * schedule and the holiday calendar, to pre-fill the create form.
     */
    public function businessDays(Request $request, BusinessDaysCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'employee' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', Company::currentOrganizationId()),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        $employee = User::query()
            ->where('organization_id', Company::currentOrganizationId())
            ->findOrFail($request->integer('employee'));

        $businessDays = $calculator->calculate(
            $employee,
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
        );

        return response()->json(['business_days' => $businessDays]);
    }

    /**
     * @throws Throwable
     */
    public function approve(Leave $leave, LeaveManager $manager): RedirectResponse
    {
        Gate::authorize('approve', $leave);

        abort_if($leave->status === LeaveStatus::Approved, 403);

        $manager->approve($leave);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.leaves.flash.approved')]);

        return back();
    }

    /**
     * @throws Throwable
     */
    public function reject(Leave $leave, LeaveManager $manager): RedirectResponse
    {
        Gate::authorize('reject', $leave);

        // Medical leaves are auto-approved and cannot be rejected.
        abort_if($leave->status === LeaveStatus::Rejected || $leave->type === LeaveType::Medical, 403);

        $manager->reject($leave);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.leaves.flash.rejected')]);

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(Leave $leave, LeaveManager $manager): RedirectResponse
    {
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
     * Employees of the current organization for the select/filter inputs. When
     * a supervisor id is given, the list is scoped to that supervisor's team.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function employeeOptions(?int $supervisorId = null): array
    {
        return User::query()
            ->employees()
            ->where('organization_id', Company::currentOrganizationId())
            ->when($supervisorId, fn ($query) => $query->where('supervisor_id', $supervisorId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $employee) => ['value' => (string) $employee->id, 'label' => $employee->name])
            ->all();
    }
}
