<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRestDayBalance;
use App\Services\Overtime\RestDayBalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An employee's own rest-day compensation balance (KOL-47 AC #6), read-only —
 * consumption is registered by HR, not requested here yet (see the task's
 * notes on the 48-hour self-service notice requirement).
 */
class OvertimeRestDayBalanceController extends Controller
{
    public function index(Request $request, RestDayBalanceService $balances): Response
    {
        $user = $request->user();

        $lines = OvertimeRestDayBalance::query()
            ->forUser($user->id)
            ->orderByDesc('accrual_date')
            ->get();

        return Inertia::render('my/overtime-rest-day-balance/index', [
            'available' => (string) $balances->availableBalance($user),
            'lines' => $lines->map(fn (OvertimeRestDayBalance $line) => [
                'id' => $line->id,
                'accrued_hours' => $line->accrued_hours,
                'rest_hours' => $line->rest_hours,
                'consumed_hours' => $line->consumed_hours,
                'remaining_hours' => (string) $line->remaining(),
                'accrual_date' => $line->accrual_date->format('Y-m-d'),
                'expiry_date' => $line->expiry_date->format('Y-m-d'),
                'status' => $line->isExpired()
                    ? ['value' => 'expired', 'label' => __('ui.overtime.rest_day_balances.statuses.expired'), 'variant' => 'destructive']
                    : ['value' => 'active', 'label' => __('ui.overtime.rest_day_balances.statuses.active'), 'variant' => 'default'],
            ]),
        ]);
    }
}
