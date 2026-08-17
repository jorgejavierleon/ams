<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Exceptions\RestDayBalanceRefused;
use App\Models\OvertimeRestDayBalance;
use App\Models\User;
use App\Services\Overtime\RestDayBalanceService;
use App\Support\Duration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HR/admin view of every employee's rest-day compensation balance (KOL-47 AC
 * #6), gated by the same `Manage:OvertimeAuthorization` permission as the
 * pactos it is built on. Consumption is registered here rather than through
 * self-service — see the task's notes on the 48-hour notice requirement this
 * does not yet implement.
 */
class OvertimeRestDayBalanceController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['accrual_date', 'expiry_date'],
            'expiry_date',
            'asc',
        );

        $balances = OvertimeRestDayBalance::query()
            ->with('user:id,name')
            ->when($search, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('name', 'like', "%{$search}%"),
            ))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('overtime/rest-day-balances/index', [
            'balances' => $balances->through(fn (OvertimeRestDayBalance $balance) => [
                'id' => $balance->id,
                'user_id' => $balance->user_id,
                'employee' => $balance->user?->name,
                'accrued_hours' => $balance->accrued_hours,
                'rest_hours' => $balance->rest_hours,
                'consumed_hours' => $balance->consumed_hours,
                'remaining_hours' => (string) $balance->remaining(),
                'accrual_date' => $balance->accrual_date->format('Y-m-d'),
                'expiry_date' => $balance->expiry_date->format('Y-m-d'),
                'status' => $balance->isExpired()
                    ? ['value' => 'expired', 'label' => __('ui.overtime.rest_day_balances.statuses.expired'), 'variant' => 'destructive']
                    : ['value' => 'active', 'label' => __('ui.overtime.rest_day_balances.statuses.active'), 'variant' => 'default'],
                'payable_from_expiry' => $balance->isExpired() ? (string) $balance->payableFromExpiry() : null,
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
            'employeeOptions' => $this->employeeOptions(),
        ]);
    }

    public function consume(Request $request, RestDayBalanceService $balances): RedirectResponse
    {
        $organizationId = OvertimeRestDayBalance::currentOrganizationId();

        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'hours' => ['required', 'date_format:H:i', 'after:00:00'],
            'consumed_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'hours.after' => __('ui.overtime.rest_day_balances.validation.positive_hours'),
        ]);

        $user = User::query()
            ->where('organization_id', $organizationId)
            ->where('id', $data['user_id'])
            ->firstOrFail();

        try {
            $balances->consume(
                $user,
                Duration::fromTimeString($data['hours'].':00'),
                $data['note'] ?? null,
                $request->user(),
                Carbon::parse($data['consumed_on']),
            );
        } catch (RestDayBalanceRefused) {
            throw ValidationException::withMessages([
                'hours' => __('ui.overtime.rest_day_balances.validation.insufficient_balance'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.overtime.rest_day_balances.flash.consumed')]);

        return back();
    }

    /**
     * Employees of the current organization for the consume form's select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function employeeOptions(): array
    {
        return User::query()
            ->employees()
            ->where('organization_id', OvertimeRestDayBalance::currentOrganizationId())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $employee) => ['value' => (string) $employee->id, 'label' => $employee->name])
            ->all();
    }
}
