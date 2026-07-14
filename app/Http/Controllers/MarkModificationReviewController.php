<?php

namespace App\Http\Controllers;

use App\Managers\MarkModificationManager;
use App\Models\MarkModification;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, no-auth review of a requested attendance-mark correction. The
 * employee reaches these routes through the ULID link emailed to them and
 * approves or declines the change without logging in.
 */
class MarkModificationReviewController extends Controller
{
    /**
     * Render the review page for the modification identified by its ULID.
     */
    public function show(MarkModification $modification): Response
    {
        $modification->load(['user', 'mark']);

        return Inertia::render('mark-modifications/review', [
            'modification' => $this->present($modification),
        ]);
    }

    /**
     * Approve the correction and return to the review page, which now reflects
     * the approved state.
     */
    public function approve(MarkModification $modification, MarkModificationManager $manager): RedirectResponse
    {
        if (! $modification->isActionable()) {
            return to_route('mark-modifications.review', $modification);
        }

        $manager->approve($modification);

        return to_route('mark-modifications.review', $modification);
    }

    /**
     * Decline the correction and return to the review page, which now reflects
     * the declined state.
     */
    public function decline(MarkModification $modification, MarkModificationManager $manager): RedirectResponse
    {
        if (! $modification->isActionable()) {
            return to_route('mark-modifications.review', $modification);
        }

        $manager->decline($modification);

        return to_route('mark-modifications.review', $modification);
    }

    /**
     * Shape the modification for the review page, resolving the current review
     * state (pending, expired, approved or declined) the UI branches on.
     *
     * @return array{
     *     ulid: string,
     *     employee_name: string,
     *     original_date_time: string|null,
     *     proposed_date_time: string,
     *     mark_type: string|null,
     *     reason: string|null,
     *     notes: string|null,
     *     state: string,
     * }
     */
    private function present(MarkModification $modification): array
    {
        return [
            'ulid' => $modification->ulid,
            'employee_name' => $modification->user->name,
            'original_date_time' => $modification->mark?->date_time?->format('d-m-Y H:i'),
            'proposed_date_time' => $modification->date_time->format('d-m-Y H:i'),
            'mark_type' => $modification->mark_type?->label(),
            'reason' => $modification->reason?->label(),
            'notes' => $modification->notes,
            'state' => $this->resolveState($modification),
        ];
    }

    /**
     * Collapse status and expiry into the single state string the page renders.
     */
    private function resolveState(MarkModification $modification): string
    {
        if ($modification->isExpired()) {
            return 'expired';
        }

        return $modification->status->value;
    }
}
