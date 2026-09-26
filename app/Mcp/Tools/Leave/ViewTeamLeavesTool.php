<?php

namespace App\Mcp\Tools\Leave;

use App\Enums\LeaveStatus;
use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\Leave\Concerns\FormatsLeave;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Team leave review listing. Mirrors LeaveController::index 1:1: admins see
 * every request, a supervisor sees only their direct reports' requests.
 */
#[Name('view-team-leaves')]
#[Description("List your team's leave requests. Admins see every request in the organization; a supervisor sees only their direct reports'.")]
class ViewTeamLeavesTool extends AuthorizedTool
{
    use FormatsLeave;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_map(fn (LeaveStatus $status): string => $status->value, LeaveStatus::cases()))
                ->description('Only return leaves in this status. Omit for every status.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'viewTeam', Leave::class)) {
            return $response;
        }

        /** @var User $user */
        $user = $request->user();
        $isOrgWide = $user->hasRole('admin') || $user->isOwner();
        $supervisorId = $isOrgWide ? null : $user->id;

        $status = LeaveStatus::tryFrom((string) $request->get('status'));

        $leaves = Leave::query()
            ->with('user:id,name')
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($employee) => $employee->where('supervisor_id', $supervisorId),
            ))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('start_date')
            ->get();

        return Response::structured([
            'leaves' => $leaves->map(fn (Leave $leave) => $this->formatLeave($leave))->all(),
        ]);
    }
}
