<?php

namespace App\Mcp\Tools\Overtime;

use App\Enums\OvertimeRequestStatus;
use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\Overtime\Concerns\FormatsOvertimeRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\Workday;
use App\Notifications\OvertimeRequestSubmitted;
use App\Services\OrganizationSettings;
use App\Services\OvertimeRequestApprovers;
use App\Services\TimeZoneService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Self-service overtime request. Mirrors My\OvertimeRequestController::store
 * 1:1: the requester is always the authenticated employee, an optional
 * workday_id (KOL-79) forces the hours to that day's already-computed
 * figure, and the request is refused outside the tenant's retroactive
 * window or when the tenant's mode does not allow requests at all.
 */
#[Name('create-overtime-request')]
#[Description('Request overtime for yourself, exactly like the self-service web form.')]
class CreateOvertimeRequestTool extends AuthorizedTool
{
    use FormatsOvertimeRequest;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()
                ->description('The date the overtime was or will be worked, as YYYY-MM-DD.')
                ->required(),
            'requested_hours' => $schema->string()
                ->description('Hours requested, as HH:MM (e.g. 02:00). Must be greater than 00:00. Ignored when workday_id is given.')
                ->required(),
            'reason' => $schema->string()
                ->max(1000)
                ->description('Optional note for the approver.'),
            'workday_id' => $schema->integer()
                ->description("Optional id of one of your own Workdays with already-calculated overtime. When given, the request's hours and date come from that day's calculated figure rather than requested_hours/date."),
        ];
    }

    public function handle(Request $request, OrganizationSettings $settings, OvertimeRequestApprovers $approvers, TimeZoneService $timeZone): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'RequestOwn:OvertimeAuthorization')) {
            return $response;
        }

        if (! $settings->overtimeAuthorizationMode()->allowsRequests()) {
            return Response::error('Overtime requests are not enabled for your organization.');
        }

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'requested_hours' => ['required', 'date_format:H:i', 'after:00:00'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'workday_id' => ['nullable', 'integer'],
        ], [
            'requested_hours.after' => __('ui.overtime.requests.validation.positive_hours'),
        ]);

        $requestedHours = $data['requested_hours'].':00';

        if (! empty($data['workday_id'])) {
            $workday = Workday::query()
                ->where('user_id', $user->id)
                ->where('id', $data['workday_id'])
                ->first();

            if ($workday === null) {
                return Response::error('Workday not found.');
            }

            if (! $workday->calculated_overtime || $workday->calculated_overtime === '00:00:00') {
                throw ValidationException::withMessages([
                    'workday_id' => __('ui.overtime.requests.validation.no_calculated_overtime'),
                ]);
            }

            $requestedHours = $workday->calculated_overtime;
        }

        $date = Carbon::parse($data['date'], $timeZone->getAppTimezone())->startOfDay();
        $windowDays = $settings->overtimeRetroactiveRequestDays();
        $earliestAllowed = $timeZone->today()->subDays($windowDays);

        if ($date->lessThan($earliestAllowed)) {
            throw ValidationException::withMessages([
                'date' => __('ui.overtime.requests.validation.retroactive_window', ['days' => $windowDays]),
            ]);
        }

        $overtimeRequest = OvertimeRequest::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'date' => $date,
            'requested_hours' => $requestedHours,
            'reason' => $data['reason'] ?? null,
            'status' => OvertimeRequestStatus::Pending,
        ]);

        Notification::send(
            $approvers->submissionRecipients($overtimeRequest),
            new OvertimeRequestSubmitted($overtimeRequest),
        );

        return Response::structured($this->formatOvertimeRequest($overtimeRequest));
    }
}
