<?php

namespace App\Mcp\Tools\Overtime;

use App\Enums\OvertimeRequestStatus;
use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\Overtime\Concerns\FormatsOvertimeRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Team overtime-request review listing. Mirrors OvertimeRequestController::index
 * 1:1: an admin or the organization Owner sees every request, a supervisor
 * sees only their direct reports', and the default view is pending-only
 * unless a status (or "all") is given.
 */
#[Name('view-team-overtime-requests')]
#[Description('List your team\'s overtime requests. Admins and the organization Owner see every request; a supervisor sees only their direct reports\'. Defaults to pending requests only.')]
class ViewTeamOvertimeRequestsTool extends AuthorizedTool
{
    use FormatsOvertimeRequest;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum([...array_map(fn (OvertimeRequestStatus $status): string => $status->value, OvertimeRequestStatus::cases()), 'all'])
                ->description('Only return requests in this status. Defaults to pending. Pass "all" for every status.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'viewTeam', OvertimeRequest::class)) {
            return $response;
        }

        /** @var User $user */
        $user = $request->user();
        $isOrgWide = $user->hasRole('admin') || $user->isOwner();
        $supervisorId = $isOrgWide ? null : $user->id;

        $status = $this->statusFilter($request);

        $requests = OvertimeRequest::query()
            ->with(['user:id,name', 'reviewedBy:id,name'])
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($employee) => $employee->where('supervisor_id', $supervisorId),
            ))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('date')
            ->get();

        return Response::structured([
            'requests' => $requests->map(fn (OvertimeRequest $overtimeRequest) => $this->formatOvertimeRequest($overtimeRequest))->all(),
        ]);
    }

    /**
     * Resolve the status filter, treating an absent status as "pending" (the
     * pending-requests screen first, decision history second) and "all" as no
     * filter — mirrors OvertimeRequestController::statusFilter().
     */
    private function statusFilter(Request $request): ?OvertimeRequestStatus
    {
        if (! $request->has('status')) {
            return OvertimeRequestStatus::Pending;
        }

        $value = $request->string('status')->trim()->value();

        return $value === '' || $value === 'all' ? null : OvertimeRequestStatus::tryFrom($value);
    }
}
