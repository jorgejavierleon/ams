<?php

namespace App\Mcp\Tools\Leave;

use App\Enums\LeaveStatus;
use App\Managers\LeaveManager;
use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\Leave\Concerns\FormatsLeave;
use App\Models\Leave;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

/**
 * Approve a team member's leave. Mirrors LeaveController::approve 1:1: an
 * admin may approve any request; a supervisor only their own team's, and
 * only while holding ApproveTeam:Leave.
 */
#[Name('approve-leave')]
#[Description("Approve a team member's pending leave request.")]
class ApproveLeaveTool extends AuthorizedTool
{
    use FormatsLeave;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leave_id' => $schema->integer()
                ->description('The id of the leave request to approve.')
                ->required(),
        ];
    }

    /**
     * @throws Throwable
     */
    public function handle(Request $request, LeaveManager $manager): Response|ResponseFactory
    {
        $data = $request->validate([
            'leave_id' => ['required', 'integer'],
        ]);

        $leave = Leave::find((int) $data['leave_id']);

        if ($leave === null) {
            return Response::error('Leave request not found.');
        }

        if ($response = $this->authorize($request, 'approve', $leave)) {
            return $response;
        }

        if ($leave->status === LeaveStatus::Approved) {
            return Response::error('This leave request is already approved.');
        }

        $manager->approve($leave);
        $leave->refresh();

        return Response::structured($this->formatLeave($leave));
    }
}
