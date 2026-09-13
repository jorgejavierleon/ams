<?php

namespace App\Services;

use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Enums\OvertimeAuthorizationStatus;
use App\Enums\OvertimeCompensationType;
use App\Models\MarkModification;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use App\Support\Rut;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * Shapes a workday and its mark-modification history for the detail screens.
 * Shared by the admin workday view and the employee self-service view so both
 * render the same KPIs, attendance data and modification timeline; the only
 * difference between them is which actions each surface around this data.
 */
class WorkdayPresenter
{
    /**
     * Shape a workday for the detail page: identity, computed totals and each
     * mark card with its current modification state.
     *
     * @return array<string, mixed>
     */
    public function workday(Workday $workday): array
    {
        return [
            'id' => $workday->id,
            'date' => $workday->date->format('Y-m-d'),
            'date_label' => $workday->date->isoFormat('dddd D [de] MMMM [de] YYYY'),
            'employee' => [
                'id' => $workday->user_id,
                'name' => $workday->user?->name,
            ],
            'status' => $workday->status?->value,
            'status_label' => $workday->status?->label(),
            'status_badge' => $workday->status?->badge(),
            'shift' => $workday->shift?->name,
            'shift_timeframe' => $this->shiftTimeframe($workday),
            'shift_start' => $this->timeOfDay($workday->shift_start_time),
            'shift_end' => $this->timeOfDay($workday->shift_end_time),
            'premise' => $workday->premise?->name,
            'leave' => $workday->leave === null ? null : [
                'type' => $workday->leave->type->label(),
                'start_date' => $workday->leave->start_date->format('d/m/Y'),
                'end_date' => $workday->leave->end_date->format('d/m/Y'),
            ],
            'mark_in' => $this->presentMark($workday, MarkType::In),
            'mark_out' => $this->presentMark($workday, MarkType::Out),
            'worked_time' => $this->trimSeconds($workday->worked_time),
            'extra_time' => $this->trimSeconds($workday->extra_time),
            'missing_time' => $this->trimSeconds($workday->missing_time),
            // KOL-79: the day's raw calculated overtime, so the employee's own
            // detail page can offer to request it without recalculating what
            // they worked. `overtime()` below carries the fuller admin/
            // supervisor picture (authorization status, decide/revoke), which
            // does not apply to the employee's own view.
            'calculated_overtime' => $this->trimSeconds($workday->calculated_overtime),
        ];
    }

    /**
     * Shape the workday's whole mark-modification history, most recent first.
     * Used as-is by the employee self-service detail page; the admin/
     * supervisor Jornadas detail page uses {@see self::timeline()} instead,
     * which merges this same data with the day's overtime decision.
     *
     * @return array<int, array<string, mixed>>
     */
    public function modifications(Workday $workday): array
    {
        return $workday->markModifications
            ->map(fn (MarkModification $modification) => $this->modification($modification))
            ->all();
    }

    /**
     * KOL-71: the day's overtime figures and status for the detail page's
     * stat section, or null when the day carries no calculated overtime.
     * `overtimeAuthorization` (and its `user`) must already be eager-loaded
     * on `$workday`.
     *
     * @return array<string, mixed>|null
     */
    public function overtime(Workday $workday): ?array
    {
        if (! $workday->calculated_overtime || $workday->calculated_overtime === '00:00:00') {
            return null;
        }

        $authorization = $workday->overtimeAuthorization;
        $isApproved = $authorization?->isApproved() ?? false;

        return [
            'calculated_hours' => $this->trimSeconds($workday->calculated_overtime),
            'authorized_hours' => $this->trimSeconds($authorization?->authorized_hours),
            'final_hours' => $this->trimSeconds($authorization?->final_hours),
            'status' => $authorization?->status->value ?? 'not_opened',
            'status_label' => $authorization?->status->label() ?? __('ui.workdays.overtime.statuses.not_opened'),
            'status_badge' => $authorization?->status->badge() ?? 'outline',
            'compensation_type_label' => $isApproved ? $authorization->compensation_type->label() : null,
            'compensation_eligible' => $workday->user->overtime_rest_day_eligible,
            'can_decide' => ! $isApproved && Gate::allows('approve', $authorization ?? $this->provisionalAuthorization($workday)),
            'can_revoke' => $isApproved && Gate::allows('revoke', $authorization),
        ];
    }

    /**
     * A transient, unsaved OvertimeAuthorization for permission checks only.
     * KOL-80: a day nobody has acted on has no persisted row, so "may this
     * user decide it" cannot be answered by loading one. The policy only
     * reads `$authorization->user->supervisor_id`, so an in-memory instance
     * carrying that relation (already eager-loaded on the workday) answers
     * the same question without writing anything or an extra query.
     */
    private function provisionalAuthorization(Workday $workday): OvertimeAuthorization
    {
        return (new OvertimeAuthorization([
            'organization_id' => $workday->organization_id,
            'user_id' => $workday->user_id,
        ]))->setRelation('user', $workday->user);
    }

    /**
     * KOL-71: the mark-modification history and the day's overtime decisions
     * merged into one chronological feed, most recently acted-on first — so
     * the Jornadas detail page reads as a single audit trail rather than two
     * disconnected lists. `overtimeAuthorization.activities.causer` must
     * already be eager-loaded on `$workday` (KOL-82).
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(Workday $workday): array
    {
        $entries = $workday->markModifications
            ->map(fn (MarkModification $modification) => [
                'sort_at' => ($modification->reviewed_at ?? $modification->created_at)->timestamp,
                'sort_seq' => $modification->id,
                ...$this->modification($modification),
            ])
            ->all();

        foreach ($this->overtimeTimelineEntries($workday) as $entry) {
            $entries[] = $entry;
        }

        // KOL-82: `created_at` only has second precision, so two activities
        // logged within the same second (an approval immediately revoked)
        // would otherwise tie on `sort_at` and fall back to array order. The
        // row's own auto-increment id breaks that tie correctly.
        $sorted = collect($entries)
            ->sortByDesc(['sort_at', 'sort_seq'])
            ->values();

        // can_decide/can_revoke reflect the record's *current* state, so only
        // the most recent overtime entry — wherever it lands once merged with
        // the mark-modification history — carries them; older entries are
        // pure history.
        $currentOvertimeIndex = $sorted->search(fn (array $entry) => $entry['kind'] === 'overtime');

        if ($currentOvertimeIndex !== false) {
            $authorization = $workday->overtimeAuthorization;
            $isApproved = $authorization->isApproved();
            $canDecide = ! $isApproved && Gate::allows('approve', $authorization);
            $canRevoke = $isApproved && Gate::allows('revoke', $authorization);

            $sorted = $sorted->map(function (array $entry, int $index) use ($currentOvertimeIndex, $canDecide, $canRevoke) {
                if ($index !== $currentOvertimeIndex) {
                    return $entry;
                }

                $entry['can_decide'] = $canDecide;
                $entry['can_revoke'] = $canRevoke;

                return $entry;
            });
        }

        return $sorted
            ->map(fn (array $entry) => Arr::except($entry, ['sort_at', 'sort_seq']))
            ->all();
    }

    /**
     * KOL-82: every approve/revoke decision logged against the day's overtime
     * as its own timeline entry, oldest first — so approving a day and later
     * revoking it shows both events instead of the revocation silently
     * replacing the approval the way the plain status columns would.
     * Empty when the day has no OvertimeAuthorization row.
     *
     * A record decided before this feature shipped logged nothing for that
     * decision, so its `approved`/`revoked` entry is synthesised from the
     * still-present columns instead — the log is additive, so history that
     * predates it must not simply vanish from the timeline.
     *
     * @return array<int, array<string, mixed>>
     */
    private function overtimeTimelineEntries(Workday $workday): array
    {
        $authorization = $workday->overtimeAuthorization;

        if ($authorization === null) {
            return [];
        }

        $activities = $authorization->activities;

        $entries = $activities
            ->map(fn (Activity $activity) => $this->overtimeEntry(
                id: $activity->id,
                event: $activity->event,
                calculatedHours: $activity->getProperty('calculated_hours') ?? $authorization->calculated_hours,
                authorizedHours: $activity->getProperty('authorized_hours'),
                finalHours: $activity->getProperty('final_hours'),
                compensationType: $activity->getProperty('compensation_type'),
                reason: $activity->getProperty('reason'),
                causerName: ($causer = $activity->causer) instanceof User ? $causer->name : null,
                occurredAt: $activity->created_at,
                sortSeq: $activity->id,
            ))
            ->all();

        if (! $activities->contains('event', 'approved') && $authorization->reviewed_by !== null) {
            $entries[] = $this->overtimeEntry(
                id: -($authorization->id * 2),
                event: 'approved',
                calculatedHours: $authorization->calculated_hours,
                authorizedHours: $authorization->authorized_hours,
                finalHours: $authorization->final_hours,
                compensationType: $authorization->compensation_type->value,
                reason: $authorization->reason,
                causerName: $authorization->reviewedBy?->name,
                occurredAt: $authorization->reviewed_at,
                sortSeq: -1,
            );
        }

        if (! $activities->contains('event', 'revoked') && $authorization->revoked_by !== null) {
            $entries[] = $this->overtimeEntry(
                id: -($authorization->id * 2 + 1),
                event: 'revoked',
                calculatedHours: $authorization->calculated_hours,
                authorizedHours: $authorization->authorized_hours,
                finalHours: $authorization->final_hours,
                compensationType: $authorization->compensation_type->value,
                reason: $authorization->revoked_reason,
                causerName: $authorization->revokedBy?->name,
                occurredAt: $authorization->revoked_at,
                sortSeq: -1,
            );
        }

        return $entries;
    }

    /**
     * Shape one `approved`/`revoked` overtime timeline entry from already-
     * resolved values, whichever source they came from (a logged
     * {@see Activity} or, for a decision predating KOL-82, the record's own
     * columns). `can_decide`/`can_revoke` are always false here — the caller
     * ({@see self::timeline()}) grants them only to the single most recent
     * overtime entry once every entry is merged and sorted.
     *
     * @return array<string, mixed>
     */
    private function overtimeEntry(
        int $id,
        string $event,
        ?string $calculatedHours,
        ?string $authorizedHours,
        ?string $finalHours,
        ?string $compensationType,
        ?string $reason,
        ?string $causerName,
        ?CarbonInterface $occurredAt,
        int $sortSeq,
    ): array {
        $status = OvertimeAuthorizationStatus::from($event);
        $occurredAtLabel = $occurredAt?->format('d/m/Y H:i');
        $occurredAgo = $occurredAt?->diffForHumans();

        return [
            'id' => $id,
            'kind' => 'overtime',
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_badge' => $status->badge(),
            'calculated_hours' => $this->trimSeconds($calculatedHours),
            'authorized_hours' => $this->trimSeconds($authorizedHours),
            'final_hours' => $this->trimSeconds($finalHours),
            'compensation_type_label' => $compensationType === null
                ? null
                : OvertimeCompensationType::from($compensationType)->label(),
            'reason' => $reason,
            'created_at' => $occurredAtLabel,
            'created_ago' => $occurredAgo,
            'reviewed_by' => $causerName,
            'reviewed_at' => $occurredAtLabel,
            'reviewed_ago' => $occurredAgo,
            'can_decide' => false,
            'can_revoke' => false,
            'sort_at' => $occurredAt === null ? 0 : $occurredAt->timestamp,
            'sort_seq' => $sortSeq,
        ];
    }

    /**
     * Shape one mark card: its registered time, whether it currently carries a
     * pending or already-applied correction, and the full legal snapshot shown
     * in the mark-detail dialog.
     *
     * @return array<string, mixed>
     */
    private function presentMark(Workday $workday, MarkType $type): array
    {
        $mark = $type === MarkType::In ? $workday->markIn : $workday->markOut;
        $typeModifications = $workday->markModifications->where('mark_type', $type);

        return [
            'type' => $type->value,
            'time' => $mark?->date_time?->format('H:i:s'),
            'scheduled' => $this->timeOfDay(
                $type === MarkType::In ? $workday->shift_start_time : $workday->shift_end_time,
            ),
            'has_pending' => $typeModifications->contains(fn (MarkModification $modification) => $modification->isPending()),
            'is_modified' => $typeModifications->contains(
                fn (MarkModification $modification) => $modification->status === MarkModificationStatus::Approved,
            ),
            'details' => $mark === null ? null : [
                'date' => $mark->date_time->format('d/m/Y'),
                'time' => $mark->date_time->format('H:i:s'),
                'type' => $mark->type->label(),
                'shift' => $mark->shift_start_time && $mark->shift_end_time
                    ? $mark->shift_start_time->format('H:i').' - '.$mark->shift_end_time->format('H:i')
                    : null,
                'employee_name' => $mark->employee_name,
                'employee_rut' => $mark->employee_rut === null ? null : Rut::format($mark->employee_rut),
                'employer_name' => $mark->employer_name,
                'employer_rut' => $mark->employer_rut === null ? null : Rut::format($mark->employer_rut),
                'premise_name' => $mark->premise_name,
                'premise_address' => $mark->premise_address,
                'coordinates' => $mark->lat && $mark->lng ? $mark->lat.', '.$mark->lng : null,
            ],
        ];
    }

    /**
     * Shape one mark-modification history row: its proposed change, review state
     * and the full audit trail (who requested and reviewed it, and when).
     *
     * @return array<string, mixed>
     */
    private function modification(MarkModification $modification): array
    {
        return [
            'id' => $modification->id,
            'kind' => 'mark_modification',
            'mark_type' => $modification->mark_type?->value,
            'mark_type_label' => $modification->mark_type?->label(),
            'status' => $modification->status?->value,
            'status_label' => $modification->status?->label(),
            'status_badge' => $modification->status?->badge(),
            'original_time' => ($modification->original_date_time ?? $modification->mark?->date_time)?->format('H:i:s'),
            'modified_time' => $modification->date_time->format('H:i:s'),
            'reason' => $modification->reason?->label(),
            'notes' => $modification->notes,
            'created_by' => $modification->createdBy?->name,
            'created_at' => $modification->created_at?->format('d/m/Y H:i'),
            'created_ago' => $modification->created_at?->diffForHumans(),
            'reviewed_by' => $modification->reviewedBy?->name,
            'reviewed_at' => $modification->reviewed_at?->format('d/m/Y H:i'),
            'reviewed_ago' => $modification->reviewed_at?->diffForHumans(),
            'can_review' => $this->canReview($modification),
        ];
    }

    /**
     * Whether the current user is the assigned reviewer of a still-actionable
     * request. The reviewer is the employee whose mark is being corrected, so
     * approve/decline only surface when they are the one viewing the workday.
     */
    private function canReview(MarkModification $modification): bool
    {
        return $modification->isActionable() && $modification->user_id === Auth::id();
    }

    /**
     * The workday's scheduled shift window as `HH:MM - HH:MM`, or null when no
     * shift is assigned.
     */
    private function shiftTimeframe(Workday $workday): ?string
    {
        if ($workday->shift_start_time === null || $workday->shift_end_time === null) {
            return null;
        }

        return Carbon::parse($workday->shift_start_time)->format('H:i').' - '.
            Carbon::parse($workday->shift_end_time)->format('H:i');
    }

    /**
     * Normalise a stored `TIME` value to `HH:MM`, or null when unset.
     */
    private function timeOfDay(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i');
    }

    /**
     * Drop the seconds from a stored HH:MM:SS time for compact display.
     */
    private function trimSeconds(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i');
    }
}
