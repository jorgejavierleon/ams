<?php

namespace App\Http\Controllers\Api;

use App\Enums\MarkModificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\My\WorkdayController;
use App\Http\Resources\PendingMarkModificationResource;
use App\Managers\MarkModificationManager;
use App\Models\MarkModification;
use App\Models\User;
use App\Models\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The Jornada tab's pending-correction card (kolvi-mobile KMO-35): the
 * employee's own pending mark-modification requests, and the approve/decline
 * actions on each. Mirrors {@see WorkdayController}'s
 * own pending list and review actions, ported to the mobile API surface the
 * way KOL-64/65/68 ported the rest of Jornada.
 */
class PendingMarkModificationsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $pending = MarkModification::query()
            ->where('user_id', $user->id)
            ->where('status', MarkModificationStatus::Pending)
            ->with(['mark', 'workday:id,date', 'createdBy:id,name'])
            ->latest('created_at')
            ->get();

        return PendingMarkModificationResource::collection($pending);
    }

    /**
     * Approve a correction an admin requested against one of the employee's
     * own marks. The manager rewrites the mark and recalculates the day; only
     * the owning employee may act, and only while the request is still
     * actionable.
     */
    public function approve(Request $request, Workday $workday, MarkModification $markModification, MarkModificationManager $manager): Response
    {
        $this->authorizeReview($request, $workday, $markModification);

        $manager->approve($markModification);

        return response()->noContent();
    }

    /**
     * Decline a correction an admin requested against one of the employee's
     * own marks. The request is closed without touching the underlying mark;
     * only the owning employee may act, and only while the request is still
     * actionable.
     */
    public function decline(Request $request, Workday $workday, MarkModification $markModification, MarkModificationManager $manager): Response
    {
        $this->authorizeReview($request, $workday, $markModification);

        $manager->decline($markModification);

        return response()->noContent();
    }

    /**
     * Guard a review action: the workday and its modification must both
     * belong to the authenticated employee, and the request must still be
     * actionable (pending and within its review window).
     */
    private function authorizeReview(Request $request, Workday $workday, MarkModification $modification): void
    {
        abort_unless(
            $workday->user_id === $request->user()->id
                && $modification->user_id === $request->user()->id
                && $modification->isActionable(),
            403,
        );
    }
}
