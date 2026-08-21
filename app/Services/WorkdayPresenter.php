<?php

namespace App\Services;

use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\MarkModification;
use App\Models\OvertimeAuthorization;
use App\Models\Workday;
use App\Support\Rut;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
     * KOL-71: the mark-modification history and the day's overtime decision
     * merged into one chronological feed, most recently acted-on first — so
     * the Jornadas detail page reads as a single audit trail rather than two
     * disconnected lists. `overtimeAuthorization.user`/`.reviewedBy` must
     * already be eager-loaded on `$workday`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(Workday $workday): array
    {
        $entries = $workday->markModifications
            ->map(fn (MarkModification $modification) => [
                'sort_at' => ($modification->reviewed_at ?? $modification->created_at)->timestamp,
                ...$this->modification($modification),
            ])
            ->all();

        $overtimeEntry = $this->overtimeTimelineEntry($workday);

        if ($overtimeEntry !== null) {
            $entries[] = $overtimeEntry;
        }

        return collect($entries)
            ->sortByDesc('sort_at')
            ->values()
            ->map(fn (array $entry) => Arr::except($entry, ['sort_at']))
            ->all();
    }

    /**
     * The day's overtime decision as a single timeline entry — there is only
     * ever one decision per day, never a request history like mark
     * modifications, so this is a summary of current state rather than a log
     * of events. Null when the day has no OvertimeAuthorization row yet.
     *
     * @return array<string, mixed>|null
     */
    private function overtimeTimelineEntry(Workday $workday): ?array
    {
        $authorization = $workday->overtimeAuthorization;

        if ($authorization === null) {
            return null;
        }

        $isApproved = $authorization->isApproved();

        return [
            'id' => $authorization->id,
            'kind' => 'overtime',
            'status' => $authorization->status->value,
            'status_label' => $authorization->status->label(),
            'status_badge' => $authorization->status->badge(),
            'calculated_hours' => $this->trimSeconds($authorization->calculated_hours),
            'authorized_hours' => $this->trimSeconds($authorization->authorized_hours),
            'final_hours' => $this->trimSeconds($authorization->final_hours),
            'compensation_type_label' => $isApproved ? $authorization->compensation_type->label() : null,
            'reason' => $authorization->isRevoked() ? $authorization->revoked_reason : $authorization->reason,
            'created_at' => $authorization->created_at?->format('d/m/Y H:i'),
            'created_ago' => $authorization->created_at?->diffForHumans(),
            'reviewed_by' => $authorization->reviewedBy?->name,
            'reviewed_at' => $authorization->reviewed_at?->format('d/m/Y H:i'),
            'reviewed_ago' => $authorization->reviewed_at?->diffForHumans(),
            'revoked_by' => $authorization->revokedBy?->name,
            'revoked_at' => $authorization->revoked_at?->format('d/m/Y H:i'),
            'revoked_ago' => $authorization->revoked_at?->diffForHumans(),
            'can_decide' => ! $isApproved && Gate::allows('approve', $authorization),
            'can_revoke' => $isApproved && Gate::allows('revoke', $authorization),
            'sort_at' => ($authorization->revoked_at ?? $authorization->reviewed_at ?? $authorization->created_at)->timestamp,
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
