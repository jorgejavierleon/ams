<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Models\OvertimePact;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for the *pacto de horas extraordinarias* catalogue (KOL-42, PRD §7.6),
 * in the same shape as {@see CostCenterController}: a searchable list with an
 * inline create/edit dialog, gated by the `Manage:OvertimeAuthorization`
 * permission already introduced by KOL-43.
 *
 * There is no `destroy`: a pacto is evidence of what was agreed and when, so
 * it is only ever {@see self::revoke()}d, never deleted.
 */
class OvertimePactController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['start_date', 'end_date', 'status'],
            'start_date',
            'desc',
        );

        $pacts = OvertimePact::query()
            ->with('user:id,name')
            ->when($search, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('name', 'like', "%{$search}%"),
            ))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('overtime/pacts/index', [
            'pacts' => $pacts->through(fn (OvertimePact $pact) => [
                'id' => $pact->id,
                'user_id' => $pact->user_id,
                'employee' => $pact->user?->name,
                'start_date' => $pact->start_date->format('Y-m-d'),
                'end_date' => $pact->end_date->format('Y-m-d'),
                'status' => [
                    'value' => $pact->status->value,
                    'label' => $pact->status->label(),
                    'variant' => $pact->status->badgeVariant(),
                ],
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
            'employeeOptions' => $this->employeeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        OvertimePact::create($this->validatePact($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.pacts.flash.created')]);

        return to_route('overtime.pacts.index');
    }

    public function update(Request $request, OvertimePact $overtimePact): RedirectResponse
    {
        $overtimePact->update($this->validatePact($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.pacts.flash.updated')]);

        return to_route('overtime.pacts.index');
    }

    public function revoke(OvertimePact $overtimePact): RedirectResponse
    {
        $overtimePact->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.pacts.flash.revoked')]);

        return to_route('overtime.pacts.index');
    }

    public function activate(OvertimePact $overtimePact): RedirectResponse
    {
        $overtimePact->activate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.pacts.flash.activated')]);

        return to_route('overtime.pacts.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePact(Request $request): array
    {
        $organizationId = OvertimePact::currentOrganizationId();

        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        // Código del Trabajo art. 32: transitory, capped at three months. The
        // boundary itself is allowed — only a range that runs past it is not.
        $maxEndDate = Carbon::parse($data['start_date'])->addMonths(3);

        if (Carbon::parse($data['end_date'])->gt($maxEndDate)) {
            throw ValidationException::withMessages([
                'end_date' => __('ui.overtime.pacts.validation.exceeds_three_months'),
            ]);
        }

        return $data;
    }

    /**
     * Employees of the current organization for the create/edit form's select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function employeeOptions(): array
    {
        return User::query()
            ->employees()
            ->where('organization_id', OvertimePact::currentOrganizationId())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $employee) => ['value' => (string) $employee->id, 'label' => $employee->name])
            ->all();
    }
}
