<?php

namespace App\Mcp\Tools\Leave;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
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
 * Reject a team member's leave. Mirrors LeaveController::reject 1:1: same
 * authority as approve, a reason is required, and Medical leaves (which
 * auto-approve) cannot be rejected.
 */
#[Name('reject-leave')]
#[Description("Reject a team member's pending leave request. A reason is required.")]
class RejectLeaveTool extends AuthorizedTool
{
    use FormatsLeave;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leave_id' => $schema->integer()
                ->description('The id of the leave request to reject.')
                ->required(),
            'reason' => $schema->string()
                ->max(1000)
                ->description('Why the request is being rejected.')
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
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $leave = Leave::find((int) $data['leave_id']);

        if ($leave === null) {
            return Response::error('Leave request not found.');
        }

        if ($response = $this->authorize($request, 'reject', $leave)) {
            return $response;
        }

        if ($leave->status === LeaveStatus::Rejected || $leave->type === LeaveType::Medical) {
            return Response::error('This leave request cannot be rejected.');
        }

        $manager->reject($leave, (string) $data['reason']);
        $leave->refresh();

        return Response::structured($this->formatLeave($leave));
    }
}
