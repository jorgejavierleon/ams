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
 * Self-service overtime request listing. Mirrors
 * My\OvertimeRequestController::index 1:1: only the authenticated employee's
 * own requests.
 */
#[Name('view-own-overtime-requests')]
#[Description('List your own overtime requests and their status, optionally filtered.')]
class ViewOwnOvertimeRequestsTool extends AuthorizedTool
{
    use FormatsOvertimeRequest;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_map(fn (OvertimeRequestStatus $status): string => $status->value, OvertimeRequestStatus::cases()))
                ->description('Only return requests in this status. Omit for every status.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'ViewOwn:OvertimeAuthorization')) {
            return $response;
        }

        /** @var User $user */
        $user = $request->user();

        $status = OvertimeRequestStatus::tryFrom((string) $request->get('status'));

        $requests = OvertimeRequest::query()
            ->where('user_id', $user->id)
            ->with('reviewedBy:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('date')
            ->get();

        return Response::structured([
            'requests' => $requests->map(fn (OvertimeRequest $overtimeRequest) => $this->formatOvertimeRequest($overtimeRequest))->all(),
        ]);
    }
}
