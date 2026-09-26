<?php

namespace App\Mcp\Tools\Overtime;

use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\Overtime\Concerns\FormatsOvertimeRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\OvertimeRequestApproved;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

/**
 * Approve a team member's overtime request. Mirrors
 * OvertimeRequestController::approve 1:1: an admin or the organization Owner
 * may approve any request; a supervisor only their own team's, and only
 * while holding ApproveTeam:OvertimeAuthorization.
 */
#[Name('approve-overtime-request')]
#[Description("Approve a team member's pending overtime request.")]
class ApproveOvertimeRequestTool extends AuthorizedTool
{
    use FormatsOvertimeRequest;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'overtime_request_id' => $schema->integer()
                ->description('The id of the overtime request to approve.')
                ->required(),
        ];
    }

    /**
     * @throws Throwable
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'overtime_request_id' => ['required', 'integer'],
        ]);

        $overtimeRequest = OvertimeRequest::find((int) $data['overtime_request_id']);

        if ($overtimeRequest === null) {
            return Response::error('Overtime request not found.');
        }

        if ($response = $this->authorize($request, 'approve', $overtimeRequest)) {
            return $response;
        }

        if (! $overtimeRequest->isPending()) {
            return Response::error('This overtime request is no longer pending.');
        }

        /** @var User $user */
        $user = $request->user();

        $overtimeRequest->approve($user);
        $overtimeRequest->user->notify(new OvertimeRequestApproved($overtimeRequest));

        return Response::structured($this->formatOvertimeRequest($overtimeRequest));
    }
}
