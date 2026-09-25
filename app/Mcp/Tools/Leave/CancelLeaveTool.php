<?php

namespace App\Mcp\Tools\Leave;

use App\Enums\LeaveStatus;
use App\Managers\LeaveManager;
use App\Mcp\Tools\AuthorizedTool;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

/**
 * Self-service leave cancellation. Mirrors My\LeaveController::destroy 1:1:
 * only the owner may cancel, and only while the leave is still pending.
 */
#[Name('cancel-leave')]
#[Description('Cancel one of your own leave requests while it is still pending.')]
class CancelLeaveTool extends AuthorizedTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leave_id' => $schema->integer()
                ->description('The id of the leave request to cancel.')
                ->required(),
        ];
    }

    /**
     * @throws Throwable
     */
    public function handle(Request $request, LeaveManager $manager): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'CancelOwn:Leave')) {
            return $response;
        }

        $data = $request->validate([
            'leave_id' => ['required', 'integer'],
        ]);

        $leave = Leave::find((int) $data['leave_id']);

        if ($leave === null) {
            return Response::error('Leave request not found.');
        }

        /** @var User $user */
        $user = $request->user();

        if ($leave->user_id !== $user->id || $leave->status !== LeaveStatus::Pending) {
            return Response::error('You are not authorized to cancel this leave request.');
        }

        $manager->delete($leave);

        return Response::structured(['id' => $data['leave_id'], 'cancelled' => true]);
    }
}
