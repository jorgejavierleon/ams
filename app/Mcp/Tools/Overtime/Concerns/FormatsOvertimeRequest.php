<?php

namespace App\Mcp\Tools\Overtime\Concerns;

use App\Models\OvertimeRequest;

/**
 * Shared structured-content shape for every overtime-request MCP tool's
 * response, so an agent sees the same fields regardless of which tool
 * produced the request.
 */
trait FormatsOvertimeRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function formatOvertimeRequest(OvertimeRequest $overtimeRequest): array
    {
        return [
            'id' => $overtimeRequest->id,
            'employee_id' => $overtimeRequest->user_id,
            'employee' => $overtimeRequest->relationLoaded('user') ? $overtimeRequest->user?->name : null,
            'date' => $overtimeRequest->date->format('Y-m-d'),
            'requested_hours' => $overtimeRequest->requested_hours,
            'reason' => $overtimeRequest->reason,
            'status' => $overtimeRequest->status->value,
            'status_label' => $overtimeRequest->status->label(),
            'reviewed_by' => $overtimeRequest->relationLoaded('reviewedBy') ? $overtimeRequest->reviewedBy?->name : null,
            'decision_reason' => $overtimeRequest->decision_reason,
        ];
    }
}
