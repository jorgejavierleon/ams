<?php

use App\Enums\OvertimeRequestStatus;
use App\Exceptions\OvertimeRequestDecisionRefused;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a request is born pending and carries the employee, date and hours', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);

    $request = OvertimeRequest::create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-20',
        'requested_hours' => '02:00:00',
        'reason' => 'Cierre de mes.',
        'status' => OvertimeRequestStatus::Pending,
    ]);

    expect($request->status)->toBe(OvertimeRequestStatus::Pending)
        ->and($request->reviewed_by)->toBeNull()
        ->and($request->reason)->toBe('Cierre de mes.');
});

test('approving a request never creates a payable hour on its own', function () {
    [$request, $supervisor] = requestFor();

    $request->approve($supervisor);

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Approved)
        ->and($request->reviewed_by)->toBe($supervisor->id)
        ->and($request->reviewed_at)->not->toBeNull()
        // There is no write path from OvertimeRequest to OvertimeAuthorization:
        // approving a request is a green light, not a payable hour.
        ->and(OvertimeAuthorization::withoutGlobalScopes()->count())->toBe(0);
});

test('rejecting a request requires a reason and never creates a payable hour', function () {
    [$request, $supervisor] = requestFor();

    expect(fn () => $request->reject($supervisor, ''))
        ->toThrow(OvertimeRequestDecisionRefused::class);

    $request->reject($supervisor, 'No se autoriza por presupuesto.');

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Rejected)
        ->and($request->decision_reason)->toBe('No se autoriza por presupuesto.')
        ->and(OvertimeAuthorization::withoutGlobalScopes()->count())->toBe(0);
});

test('a request cannot be persisted as decided without the person who decided it', function () {
    [$request] = requestFor();

    expect(fn () => $request->forceFill([
        'status' => OvertimeRequestStatus::Approved,
    ])->save())->toThrow(OvertimeRequestDecisionRefused::class);

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('a reviewer from another organization cannot decide the request', function () {
    [$request] = requestFor();

    $intruder = User::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);

    expect(fn () => $request->approve($intruder))->toThrow(OvertimeRequestDecisionRefused::class);
    expect(fn () => $request->reject($intruder, 'No.'))->toThrow(OvertimeRequestDecisionRefused::class);

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('a tenant never reads another tenant request', function () {
    [$otherRequest] = requestFor();

    $organization = Organization::factory()->create();
    $reader = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($reader);

    expect(OvertimeRequest::find($otherRequest->id))->toBeNull()
        ->and(OvertimeRequest::count())->toBe(0);
});

/**
 * A pending request and a supervisor from the same organization.
 *
 * @return array{0: OvertimeRequest, 1: User}
 */
function requestFor(): array
{
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    return [$request, $supervisor];
}
