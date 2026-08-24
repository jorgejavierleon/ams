<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\My\LeaveController;
use App\Http\Resources\LeaveResource;
use App\Managers\LeaveManager;
use App\Models\Leave;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\BusinessDaysCalculator;
use App\Services\LeaveApprovers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The Permisos tab's Mis solicitudes screen and request wizard (kolvi-mobile
 * KMO-39, KMO-41): the employee's own leave requests, their vacation balance
 * bundled alongside, cancelling a still-pending one, the wizard's type/half-day
 * options and server-computed business-day count, and submitting a new
 * request. Mirrors {@see LeaveController}'s own
 * index()/create()/store()/businessDays()/vacationBalance()/destroy(), the
 * same flow the web self-service portal already has, ported to /api/v1 the
 * way KOL-64/65/68/69/81 ported the other Jornada and mobile-API endpoints.
 */
class LeavesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->get();

        return LeaveResource::collection($leaves)
            ->additional(['vacationBalance' => $this->vacationBalance($user)]);
    }

    /**
     * The request wizard's step 1 (KMO-41.1): the self-service leave types
     * plus the half-day types, mirroring {@see LeaveController::create()}'s
     * own bundle. Medical leave is never included — LeaveObserver
     * auto-approves it, which would bypass approval entirely.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => LeaveType::selfServiceOptions(),
            'halfDayTypes' => LeaveHalfDayType::options(),
        ]);
    }

    /**
     * The wizard's review step (KMO-41.2): the business days a leave would
     * span for the authenticated employee, mirroring
     * {@see LeaveController::businessDays()} exactly.
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
     * Submit a new leave request (KMO-41.3), mirroring
     * {@see LeaveController::store()}'s validation. Unlike the web form, the
     * client never submits business_days_requested — PRD §F5 requires it
     * computed server-side, and the wizard only ever displays what
     * businessDays() already returned.
     */
    public function store(Request $request, BusinessDaysCalculator $calculator, LeaveApprovers $approvers): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)->except([LeaveType::Medical])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['boolean'],
            'half_day_type' => ['nullable', 'required_if:half_day,true', Rule::enum(LeaveHalfDayType::class)],
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
            $data['business_days_requested'] = $calculator->calculate(
                $user,
                Carbon::parse($data['start_date']),
                Carbon::parse($data['end_date']),
            );
        }

        $leave = Leave::create([
            ...$data,
            // The requester is always the authenticated employee.
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'company_id' => $user->company_id,
            'status' => LeaveStatus::Pending,
        ]);

        Notification::send(
            $approvers->submissionRecipients($leave),
            new LeaveRequestSubmitted($leave),
        );

        return response()->json(['data' => new LeaveResource($leave)], 201);
    }

    /**
     * Cancel one of the employee's own leaves while it is still pending,
     * mirroring {@see LeaveController::destroy()}'s guard exactly.
     *
     * @throws Throwable
     */
    public function destroy(Request $request, Leave $leave, LeaveManager $manager): Response
    {
        abort_unless(
            $leave->user_id === $request->user()->id && $leave->status === LeaveStatus::Pending,
            403,
        );

        $manager->delete($leave);

        return response()->noContent();
    }

    /**
     * The authenticated employee's vacation balance summary, mirroring
     * {@see LeaveController::vacationBalance()}'s exact query.
     *
     * @return array{used: float, available: float, total: float}
     */
    private function vacationBalance(User $user): array
    {
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
