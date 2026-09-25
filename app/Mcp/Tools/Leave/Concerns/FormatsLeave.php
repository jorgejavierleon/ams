<?php

namespace App\Mcp\Tools\Leave\Concerns;

use App\Enums\LeaveType;
use App\Models\Leave;

/**
 * Shared structured-content shape for every leave MCP tool's response, so an
 * agent sees the same fields regardless of which tool produced the leave.
 */
trait FormatsLeave
{
    /**
     * @return array<string, mixed>
     */
    protected function formatLeave(Leave $leave): array
    {
        return [
            'id' => $leave->id,
            'employee_id' => $leave->user_id,
            'employee' => $leave->relationLoaded('user') ? $leave->user?->name : null,
            'type' => $leave->type->value,
            'type_label' => $leave->type->label(),
            'start_date' => $leave->start_date->format('Y-m-d'),
            'end_date' => $leave->end_date->format('Y-m-d'),
            'half_day' => $leave->half_day,
            'half_day_type' => $leave->half_day_type?->value,
            'business_days_requested' => $leave->business_days_requested,
            'status' => $leave->status->value,
            'status_label' => $leave->status->label(),
            'is_medical' => $leave->type === LeaveType::Medical,
            'medical_leave_number' => $leave->medical_leave_number,
            'medical_leave_doctor' => $leave->medical_leave_doctor,
            'notes' => $leave->notes,
            'rejection_reason' => $leave->rejection_reason,
        ];
    }
}
