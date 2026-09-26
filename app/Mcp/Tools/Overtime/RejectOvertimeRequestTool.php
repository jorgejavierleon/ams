<?php

namespace App\Mcp\Tools\Overtime;

use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\Overtime\Concerns\FormatsOvertimeRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\OvertimeRequestRejected;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

/**
 * Reject a team member's overtime request. Mirrors
 * OvertimeRequestController::reject 1:1: same authority as approve, and a
 * reason is required (enforced again by OvertimeRequest::booted()).
 */
#[Name('reject-overtime-request')]
#[Description("Reject a team member's pending overtime request. A reason is required.")]
class RejectOvertimeRequestTool extends AuthorizedTool
{
    use FormatsOvertimeRequest;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'overtime_request_id' => $schema->integer()
                ->description('The id of the overtime request to reject.')
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
    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'overtime_request_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $overtimeRequest = OvertimeRequest::find((int) $data['overtime_request_id']);

        if ($overtimeRequest === null) {
            return Response::error('Overtime request not found.');
        }

        if ($response = $this->authorize($request, 'reject', $overtimeRequest)) {
            return $response;
        }

        if (! $overtimeRequest->isPending()) {
            return Response::error('This overtime request is no longer pending.');
        }

        /** @var User $user */
        $user = $request->user();

        $overtimeRequest->reject($user, (string) $data['reason']);
        $overtimeRequest->user->notify(new OvertimeRequestRejected($overtimeRequest));

        return Response::structured($this->formatOvertimeRequest($overtimeRequest));
    }
}
