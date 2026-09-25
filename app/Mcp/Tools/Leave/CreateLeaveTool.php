<?php

namespace App\Mcp\Tools\Leave;

use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\Leave\Concerns\FormatsLeave;
use App\Models\Leave;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\LeaveApprovers;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Self-service leave creation. Mirrors My\LeaveController::store 1:1: the
 * requester is always the authenticated employee, Medical is excluded
 * (LeaveObserver auto-approves it, which would bypass approval).
 */
#[Name('create-leave')]
#[Description('Request a leave of absence for yourself, exactly like the self-service web form. Medical leave cannot be requested this way.')]
class CreateLeaveTool extends AuthorizedTool
{
    use FormatsLeave;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(array_map(fn (LeaveType $type): string => $type->value, LeaveType::selfServiceCases()))
                ->description('The type of leave being requested.')
                ->required(),
            'start_date' => $schema->string()
                ->description('First day of the leave, as YYYY-MM-DD.')
                ->required(),
            'end_date' => $schema->string()
                ->description('Last day of the leave, as YYYY-MM-DD. Must be on or after start_date.')
                ->required(),
            'half_day' => $schema->boolean()
                ->description('Whether this is a half-day leave. When true, start_date and end_date must be the same day and the request always counts as 0.5 business days.')
                ->default(false),
            'half_day_type' => $schema->string()
                ->enum(array_map(fn (LeaveHalfDayType $type): string => $type->value, LeaveHalfDayType::cases()))
                ->description('Required when half_day is true.'),
            'business_days_requested' => $schema->number()
                ->min(0.5)->max(365)
                ->description('Number of business days requested. Ignored (forced to 0.5) when half_day is true.')
                ->required(),
            'notes' => $schema->string()
                ->max(1000)
                ->description('Optional note for the approver.'),
        ];
    }

    public function handle(Request $request, LeaveApprovers $approvers): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'RequestOwn:Leave')) {
            return $response;
        }

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)->except([LeaveType::Medical])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['boolean'],
            'half_day_type' => ['nullable', 'required_if:half_day,true', Rule::enum(LeaveHalfDayType::class)],
            'business_days_requested' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->boolean('half_day')) {
            if ($data['start_date'] !== $data['end_date']) {
                throw ValidationException::withMessages([
                    'end_date' => __('ui.leaves.validation.half_day_single_day'),
                ]);
            }

            $data['business_days_requested'] = 0.5;
        } else {
            $data['half_day'] = false;
            $data['half_day_type'] = null;
        }

        $leave = Leave::create([
            ...$data,
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'company_id' => $user->company_id,
            'status' => LeaveStatus::Pending,
        ]);

        Notification::send(
            $approvers->submissionRecipients($leave),
            new LeaveRequestSubmitted($leave),
        );

        return Response::structured($this->formatLeave($leave));
    }
}
