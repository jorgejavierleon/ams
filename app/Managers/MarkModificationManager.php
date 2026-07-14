<?php

namespace App\Managers;

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\MarkModification;
use App\Models\Workday;
use App\Notifications\MarkModificationRequested;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Owns the mark-modification request lifecycle. A modification captures a
 * proposed correction to an attendance mark (or the addition of a missing one)
 * and stays PENDING until the employee reviews it. Creating one notifies the
 * employee; a pending request of the same type blocks a duplicate against the
 * same workday.
 */
class MarkModificationManager
{
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
