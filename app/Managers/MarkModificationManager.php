<?php

namespace App\Managers;

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Workday;
use App\Notifications\MarkModificationRequested;
use App\Services\WorkdayCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the mark-modification request lifecycle. A modification captures a
 * proposed correction to an attendance mark (or the addition of a missing one)
 * and stays PENDING until the employee reviews it. Creating one notifies the
 * employee; a pending request of the same type blocks a duplicate against the
 * same workday. Reviewing one either rewrites the underlying mark (approve) or
 * closes the request untouched (decline).
 */
class MarkModificationManager
{
    public function __construct(private readonly WorkdayCalculator $workdayCalculator) {}

    /**
     * Approve the modification: write the proposed time onto the underlying
     * mark — creating the mark and attaching it to the workday when the request
     * adds a previously missing punch — mark the request reviewed, then
     * recalculate the workday so its totals reflect the corrected time.
     */
    public function approve(MarkModification $modification): void
    {
        DB::transaction(function () use ($modification): void {
            $mark = $modification->mark;

            if ($mark === null) {
                $workday = $modification->workday;

                // The review page is public and has no tenant context, so the
                // organization is taken from the workday rather than the
                // BelongsToOrganization stamp (which would resolve to null).
                $mark = new Mark([
                    'user_id' => $modification->user_id,
                    'type' => $modification->mark_type,
                    'date_time' => $modification->date_time,
                    'premise_id' => $workday->premise_id,
                    'shift_id' => $workday->shift_id,
                    'shift_start_time' => $workday->shift_start_time,
                    'shift_end_time' => $workday->shift_end_time,
                ]);
                $mark->organization_id = $workday->organization_id;
                $mark->save();

                $workday->update([
                    $modification->mark_type === MarkType::In ? 'mark_in_id' : 'mark_out_id' => $mark->id,
                ]);
            } else {
                $mark->update(['date_time' => $modification->date_time]);
            }

            $modification->update([
                'status' => MarkModificationStatus::Approved,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'mark_id' => $mark->id,
            ]);

            if (! $this->workdayCalculator->recalculateWorkday($modification->workday)) {
                Log::error('Failed to recalculate workday after approving mark modification', [
                    'mark_modification_id' => $modification->id,
                    'workday_id' => $modification->workday_id,
                ]);
            }
        });
    }

    /**
     * Decline the modification: close the request as reviewed without touching
     * the underlying mark or workday.
     */
    public function decline(MarkModification $modification): void
    {
        $modification->update([
            'status' => MarkModificationStatus::Declined,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Open a mark-modification request against the given workday for the
     * entry mark, the exit mark, or both, as present in `$data`. Each new
     * request notifies the employee. Requests whose mark type already has a
     * pending modification are skipped by the duplicate guard.
     *
     * @param  array{mark_in?: string|null, mark_out?: string|null, reason: MarkModificationReason, notes?: string|null}  $data
     * @return Collection<int, MarkModification>
     */
    public function modifyFromWorkday(Workday $workday, array $data): Collection
    {
        $created = new Collection;

        DB::transaction(function () use ($workday, $data, $created): void {
            if (! empty($data['mark_in'])) {
                $modification = $this->createModification(
                    $workday,
                    MarkType::In,
                    $data['mark_in'],
                    $data['reason'],
                    $data['notes'] ?? null,
                );

                if ($modification !== null) {
                    $created->push($modification);
                }
            }

            if (! empty($data['mark_out'])) {
                $modification = $this->createModification(
                    $workday,
                    MarkType::Out,
                    $data['mark_out'],
                    $data['reason'],
                    $data['notes'] ?? null,
                );

                if ($modification !== null) {
                    $created->push($modification);
                }
            }
        });

        return $created;
    }

    /**
     * Create a single pending modification for the workday's mark of the given
     * type and notify the employee. Returns null when a pending request of that
     * type already exists (the duplicate guard).
     */
    public function createModification(
        Workday $workday,
        MarkType $type,
        string $time,
        MarkModificationReason $reason,
        ?string $notes = null,
    ): ?MarkModification {
        if ($this->hasPendingModification($workday, $type)) {
            return null;
        }

        /** @var MarkModification $modification */
        $modification = MarkModification::create([
            'organization_id' => $workday->organization_id,
            'workday_id' => $workday->id,
            'mark_id' => $type === MarkType::In ? $workday->mark_in_id : $workday->mark_out_id,
            'user_id' => $workday->user_id,
            'created_by' => Auth::id(),
            'reason' => $reason,
            'status' => MarkModificationStatus::Pending,
            'date_time' => $workday->date->copy()->setTimeFromTimeString($time),
            // Snapshot the mark's current time: approving rewrites the mark, so
            // the original can no longer be read back from it afterwards.
            'original_date_time' => $type === MarkType::In ? $workday->mark_in_at : $workday->mark_out_at,
            'mark_type' => $type,
            'notes' => $notes,
        ]);

        $workday->user->notify(new MarkModificationRequested($modification));

        return $modification;
    }

    /**
     * Whether the workday already has a pending modification for the given mark
     * type. Backs the one-pending-request-per-mark guard.
     */
    public function hasPendingModification(Workday $workday, MarkType $type): bool
    {
        return $workday->markModifications()
            ->where('mark_type', $type)
            ->where('status', MarkModificationStatus::Pending)
            ->exists();
    }
}
