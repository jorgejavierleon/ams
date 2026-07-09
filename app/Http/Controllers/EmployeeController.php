<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Models\Company;
use App\Models\Position;
use App\Models\Premise;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Rules\ValidRut;
use App\Support\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'email', 'rut', 'created_at'],
            'name',
        );

        $isActive = $this->ternaryFilter($request, 'is_active');
        $isAdmin = $this->ternaryFilter($request, 'is_admin');
        $premiseIds = $this->idListFilter($request, 'premises');
        $positionIds = $this->idListFilter($request, 'positions');

        $employees = User::query()
            ->employees()
            ->where('organization_id', Company::currentOrganizationId())
            ->with(['position:id,name', 'premise:id,name'])
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('email', 'like', "%{$search}%")
                ->orWhere('rut', 'like', "%{$search}%")))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->when($isAdmin !== null, fn ($query) => $query->where('is_admin', $isAdmin))
            ->when($premiseIds, fn ($query) => $query->whereIn('premise_id', $premiseIds))
            ->when($positionIds, fn ($query) => $query->whereIn('position_id', $positionIds))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees->through(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'rut' => $employee->formatted_rut,
                'avatar' => $employee->avatar,
                'position' => $employee->position?->name,
                'premise' => $employee->premise?->name,
                'is_active' => $employee->is_active,
                'is_admin' => $employee->is_admin,
            ]),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'is_active' => $isActive === null ? null : ($isActive ? '1' : '0'),
                'is_admin' => $isAdmin === null ? null : ($isAdmin ? '1' : '0'),
                'premises' => array_map('strval', $premiseIds),
                'positions' => array_map('strval', $positionIds),
            ],
            'premiseOptions' => $this->premiseOptions(),
            'positionOptions' => $this->positionOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEmployee($request);

        // The User model carries no org-scope trait, so stamp the tenant here.
        $employee = User::create($this->prepareForStorage($data, isCreate: true) + [
            'organization_id' => Company::currentOrganizationId(),
        ]);
        $employee->assignRole('employee');

        if ($request->hasFile('avatar')) {
            $employee->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.employees.flash.created')]);

        return to_route('employees.index');
    }

    public function show(User $employee): Response
    {
        $this->assertEmployee($employee);

        $employee->load(['company:id,social_reason', 'premise:id,name', 'position:id,name', 'supervisor:id,name']);

        return Inertia::render('employees/show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'second_last_name' => $employee->second_last_name,
                'email' => $employee->email,
                'personal_email' => $employee->personal_email,
                'rut' => $employee->formatted_rut,
                'avatar' => $employee->avatar,
                'phone' => $employee->phone,
                'nationality' => $employee->nationality,
                'gender' => $employee->gender,
                'company' => $employee->company?->social_reason,
                'premise' => $employee->premise?->name,
                'position' => $employee->position?->name,
                'supervisor' => $employee->supervisor?->name,
                'contract_start_date' => $employee->contract_start_date?->format('Y-m-d'),
                'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
                'is_active' => $employee->is_active,
                'is_admin' => $employee->is_admin,
                'timezone' => $employee->timezone,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
            ],
            // Shift assignments load with the page: the Shifts tab hosts a
            // stateful add/edit form, so it must not sit behind a deferred prop
            // that resets (and unmounts the form) on every redirect-back.
            'shifts' => $this->shiftAssignments($employee),
            // Documents are still deferred — wired up by #35.
            'documents' => Inertia::defer(fn () => []),
        ]);
    }

    public function edit(User $employee): Response
    {
        $this->assertEmployee($employee);

        return Inertia::render('employees/edit', [
            'employee' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'second_last_name' => $employee->second_last_name,
                'email' => $employee->email,
                'personal_email' => $employee->personal_email,
                'rut' => $employee->formatted_rut,
                'avatar' => $employee->avatar,
                'nationality' => $employee->nationality,
                'gender' => $employee->gender,
                'is_active' => $employee->is_active,
                'company_id' => $employee->company_id,
                'premise_id' => $employee->premise_id,
                'position_id' => $employee->position_id,
                'supervisor_id' => $employee->supervisor_id,
                'contract_start_date' => $employee->contract_start_date?->format('Y-m-d'),
                'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
                'is_admin' => $employee->is_admin,
                'vacation_days' => $employee->vacation_days,
                'additional_vacation_days' => $employee->additional_vacation_days,
                'administrative_days' => $employee->administrative_days,
                'has_additional_sundays' => $employee->has_additional_sundays,
                'phone' => $employee->phone,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
                'timezone' => $employee->timezone,
            ],
            'options' => $this->formOptions($employee),
        ]);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $this->assertEmployee($employee);

        $data = $this->validateEmployee($request, $employee);

        $employee->update($this->prepareForStorage($data, isCreate: false));

        if ($request->hasFile('avatar')) {
            $employee->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.employees.flash.updated')]);

        return to_route('employees.index');
    }

    public function destroy(User $employee): RedirectResponse
    {
        $this->assertEmployee($employee);

        $employee->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.employees.flash.deleted')]);

        return to_route('employees.index');
    }

    /**
     * Flip the employee's active state from the list table's inline toggle.
     */
    public function toggleActive(User $employee): RedirectResponse
    {
        $this->assertEmployee($employee);

        $employee->update(['is_active' => ! $employee->is_active]);

        return back();
    }

    /**
     * Ensure the bound user is an employee of the current organization; the
     * User model carries no global org scope, so guard route-model binding.
     */
    private function assertEmployee(User $employee): void
    {
        abort_unless(
            $employee->organization_id === Company::currentOrganizationId()
                && ! $employee->is_legal_rep
                && $employee->hasRole('employee'),
            404,
        );
    }

    /**
     * Resolve a yes/no/all ternary filter into a boolean or null (all).
     */
    private function ternaryFilter(Request $request, string $key): ?bool
    {
        $value = $request->input($key);

        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Resolve a repeated id filter (e.g. `premises[]=1&premises[]=2`).
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
     * Map the validated payload onto the columns the User model expects.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareForStorage(array $data, bool $isCreate): array
    {
        $data['rut'] = Rut::normalize((string) $data['rut']);
        $data['name'] = trim("{$data['first_name']} {$data['last_name']}");

        if ($isCreate || filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        unset($data['avatar']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request, ?User $employee = null): array
    {
        $organizationId = Company::currentOrganizationId();

        // Multipart submissions (avatar upload) serialize booleans as strings;
        // normalise them before validation so the boolean casts store correctly.
        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'is_admin' => $request->boolean('is_admin'),
            'has_additional_sundays' => $request->boolean('has_additional_sundays'),
        ]);

        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'second_last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee)],
            'personal_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'personal_email')->ignore($employee)],
            'password' => [$employee ? 'nullable' : 'required', 'string', 'min:6'],
            'rut' => [
                'required', 'string', new ValidRut,
                Rule::unique('users', 'rut')->ignore($employee),
            ],
            'nationality' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'company_id' => [
                'required', 'integer',
                Rule::exists('companies', 'id')->where('organization_id', $organizationId),
            ],
            'premise_id' => [
                'nullable', 'integer',
                Rule::exists('premises', 'id')->where('organization_id', $organizationId),
            ],
            'position_id' => [
                'nullable', 'integer',
                Rule::exists('positions', 'id')->where('organization_id', $organizationId),
            ],
            'supervisor_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
                Rule::notIn($employee ? [$employee->id] : []),
            ],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'is_admin' => ['boolean'],
            'vacation_days' => ['nullable', 'numeric', 'min:0'],
            'additional_vacation_days' => ['nullable', 'numeric', 'min:0'],
            'administrative_days' => ['nullable', 'numeric', 'min:0'],
            'has_additional_sundays' => ['boolean'],
            'phone' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);
    }

    /**
     * The employee's shift assignments (newest first) plus the shift options
     * for the "add assignment" combobox, feeding the Shifts tab.
     *
     * @return array{assignments: array<int, array<string, mixed>>, shiftOptions: array<int, array{value: string, label: string}>}
     */
    private function shiftAssignments(User $employee): array
    {
        $assignments = $employee->shiftAssignments()
            ->with('shift:id,name')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (ShiftAssignment $assignment) => [
                'id' => $assignment->id,
                'shift' => $assignment->shift?->name,
                'start_date' => $assignment->start_date->format('Y-m-d'),
                'end_date' => $assignment->end_date?->format('Y-m-d'),
                // Status derived from the range relative to today: not yet
                // started (upcoming), already finished (ended), or in effect now.
                'status' => match (true) {
                    $assignment->start_date->gt(today()) => 'upcoming',
                    $assignment->end_date !== null && $assignment->end_date->lt(today()) => 'ended',
                    default => 'current',
                },
            ])
            ->all();

        return [
            'assignments' => $assignments,
            'shiftOptions' => Shift::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shift $shift) => ['value' => (string) $shift->id, 'label' => $shift->name])
                ->all(),
        ];
    }

    /**
     * Options shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?User $employee = null): array
    {
        return [
            'companies' => $this->companyOptions(),
            'premises' => $this->premiseOptions(),
            'positions' => $this->positionOptions(),
            'supervisors' => $this->supervisorOptions($employee),
            'timezones' => $this->timezoneOptions(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function companyOptions(): array
    {
        return Company::query()
            ->orderBy('social_reason')
            ->get(['id', 'social_reason'])
            ->map(fn (Company $company) => ['value' => (string) $company->id, 'label' => $company->social_reason])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function premiseOptions(): array
    {
        return Premise::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Premise $premise) => ['value' => (string) $premise->id, 'label' => $premise->name])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function positionOptions(): array
    {
        return Position::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Position $position) => ['value' => (string) $position->id, 'label' => $position->name])
            ->all();
    }

    /**
     * Employees eligible to supervise, excluding the record being edited.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function supervisorOptions(?User $employee = null): array
    {
        return User::query()
            ->employees()
            ->where('organization_id', Company::currentOrganizationId())
            ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function timezoneOptions(): array
    {
        return array_map(
            fn (string $tz) => ['value' => $tz, 'label' => $tz],
            timezone_identifiers_list(),
        );
    }
}
