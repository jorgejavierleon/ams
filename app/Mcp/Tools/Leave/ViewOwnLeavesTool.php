<?php

namespace App\Mcp\Tools\Leave;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
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
 * Self-service leave listing. Mirrors My\LeaveController::index 1:1: only the
 * authenticated employee's own leaves, plus their vacation balance.
 */
#[Name('view-own-leaves')]
#[Description('List your own leave requests and their status, optionally filtered, plus your vacation balance.')]
class ViewOwnLeavesTool extends AuthorizedTool
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
        if ($response = $this->authorize($request, 'ViewOwn:Leave')) {
            return $response;
        }

        /** @var User $user */
        $user = $request->user();

        $status = LeaveStatus::tryFrom((string) $request->get('status'));

        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('start_date')
            ->get();

        return Response::structured([
            'leaves' => $leaves->map(fn (Leave $leave) => $this->formatLeave($leave))->all(),
            'vacation_balance' => $this->vacationBalance($user),
        ]);
    }

    /**
     * @return array{used: float, available: float, total: float}
     */
    private function vacationBalance(User $user): array
    {
        $used = (float) Leave::query()
            ->where('user_id', $user->id)
            ->where('type', LeaveType::Vacation)
            ->where('status', LeaveStatus::Approved)
            ->sum('business_days_requested');

        $available = (float) $user->vacation_days + (float) $user->additional_vacation_days;

        return [
            'used' => $used,
            'available' => $available,
            'total' => $used + $available,
        ];
    }
}
