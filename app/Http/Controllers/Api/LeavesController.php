<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\My\LeaveController;
use App\Http\Resources\LeaveResource;
use App\Managers\LeaveManager;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Throwable;

/**
 * The Permisos tab's Mis solicitudes screen (kolvi-mobile KMO-39): the
 * employee's own leave requests, their vacation balance bundled alongside,
 * and cancelling a still-pending one. Mirrors {@see LeaveController}'s own
 * index()/vacationBalance()/destroy(), the same flow the web self-service
 * portal already has, ported to /api/v1 the way KOL-64/65/68/69 ported the
 * other Jornada and mobile-API endpoints.
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
