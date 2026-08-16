<?php

namespace App\Services;

use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who should be notified about an overtime request. Mirrors
 * {@see LeaveApprovers}: the direct supervisor is the primary approver when
 * their role currently holds `ApproveTeam:OvertimeAuthorization`; otherwise
 * approval falls back to the organization's admins, who are always copied on
 * submissions for visibility.
 */
class OvertimeRequestApprovers
{
    /**
     * @return Collection<int, User>
     */
    public function primary(OvertimeRequest $overtimeRequest): Collection
    {
        $supervisor = $overtimeRequest->user->supervisor;

        if ($supervisor && $supervisor->can('ApproveTeam:OvertimeAuthorization')) {
            return collect([$supervisor]);
        }

        return $this->admins($overtimeRequest);
    }

    /**
     * @return Collection<int, User>
     */
    public function admins(OvertimeRequest $overtimeRequest): Collection
    {
        return User::query()
            ->role('admin')
            ->where('organization_id', $overtimeRequest->organization_id)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function submissionRecipients(OvertimeRequest $overtimeRequest): Collection
    {
        return $this->primary($overtimeRequest)
            ->merge($this->admins($overtimeRequest))
            ->unique('id')
            ->values();
    }
}
