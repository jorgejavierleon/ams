<?php

use App\Enums\OvertimeAuthorizationMode;
use App\Enums\OvertimeRequestStatus;
use App\Mcp\Servers\KolviServer;
use App\Mcp\Tools\Overtime\ApproveOvertimeRequestTool;
use App\Mcp\Tools\Overtime\CreateOvertimeRequestTool;
use App\Mcp\Tools\Overtime\RejectOvertimeRequestTool;
use App\Mcp\Tools\Overtime\ViewOwnOvertimeRequestsTool;
use App\Mcp\Tools\Overtime\ViewTeamOvertimeRequestsTool;
use App\Models\Organization;
use App\Models\OvertimeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('mcp');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function mcpOvertimeOrg(OvertimeAuthorizationMode $mode = OvertimeAuthorizationMode::PreAuthorization, array $settingAttributes = []): Organization
{
    $organization = Organization::factory()->create();

    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => $mode,
        ...$settingAttributes,
    ]);

    return $organization;
}

function mcpOvertimeEmployee(?Organization $organization = null, array $attributes = []): User
{
    $organization ??= mcpOvertimeOrg();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]);
}

function mcpOvertimeAdmin(?Organization $organization = null): User
{
    $organization ??= mcpOvertimeOrg();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * A supervisor employee. When $canApprove is false we mimic an admin who has
 * revoked the team-approval permission from the shared supervisor role.
 */
function mcpOvertimeSupervisor(Organization $organization, bool $canApprove = true): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    if (! $canApprove) {
        Role::findByName('supervisor')->revokePermissionTo('ApproveTeam:OvertimeAuthorization');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    return $supervisor;
}

// --- create-overtime-request (self-service) ---

test('an employee can request overtime for themselves via the create-overtime-request tool', function () {
    $employee = mcpOvertimeEmployee();

    KolviServer::actingAs($employee)
        ->tool(CreateOvertimeRequestTool::class, [
            'date' => now()->toDateString(),
            'requested_hours' => '02:00',
            'reason' => 'Cierre de inventario.',
        ])
        ->assertOk();

    $overtimeRequest = OvertimeRequest::first();

    expect($overtimeRequest)->not->toBeNull()
        ->and($overtimeRequest->user_id)->toBe($employee->id)
        ->and($overtimeRequest->status)->toBe(OvertimeRequestStatus::Pending)
        ->and($overtimeRequest->requested_hours)->toBe('02:00:00');
});

test('an employee without RequestOwn:OvertimeAuthorization is denied by the create-overtime-request tool', function () {
    $employee = User::factory()->create(); // no roles, so no permissions

    KolviServer::actingAs($employee)
        ->tool(CreateOvertimeRequestTool::class, [
            'date' => now()->toDateString(),
            'requested_hours' => '02:00',
        ])
        ->assertHasErrors();

    expect(OvertimeRequest::count())->toBe(0);
});

test('the create-overtime-request tool refuses when the tenant mode does not allow requests', function () {
    $organization = mcpOvertimeOrg(OvertimeAuthorizationMode::PostHoc);
    $employee = mcpOvertimeEmployee($organization);

    KolviServer::actingAs($employee)
        ->tool(CreateOvertimeRequestTool::class, [
            'date' => now()->toDateString(),
            'requested_hours' => '02:00',
        ])
        ->assertHasErrors();

    expect(OvertimeRequest::count())->toBe(0);
});

test('the create-overtime-request tool refuses a zero-hour request', function () {
    $employee = mcpOvertimeEmployee();

    KolviServer::actingAs($employee)
        ->tool(CreateOvertimeRequestTool::class, [
            'date' => now()->toDateString(),
            'requested_hours' => '00:00',
        ])
        ->assertHasErrors();

    expect(OvertimeRequest::count())->toBe(0);
});

test('requesting from a workday with calculated overtime uses that figure regardless of the posted value', function () {
    $employee = mcpOvertimeEmployee();
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->toDateString(),
        'calculated_overtime' => '02:30:00',
    ]);

    KolviServer::actingAs($employee)
        ->tool(CreateOvertimeRequestTool::class, [
            'date' => $workday->date->toDateString(),
            'requested_hours' => '00:15',
            'workday_id' => $workday->id,
        ])
        ->assertOk();

    expect(OvertimeRequest::first()->requested_hours)->toBe('02:30:00');
});

test('a workday belonging to another employee is refused by the create-overtime-request tool', function () {
    $employee = mcpOvertimeEmployee();
    $intruder = mcpOvertimeEmployee();
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->toDateString(),
        'calculated_overtime' => '02:00:00',
    ]);

    KolviServer::actingAs($intruder)
        ->tool(CreateOvertimeRequestTool::class, [
            'date' => $workday->date->toDateString(),
            'requested_hours' => '02:00',
            'workday_id' => $workday->id,
        ])
        ->assertHasErrors();

    expect(OvertimeRequest::count())->toBe(0);
});

test('a retroactive request outside the tenant window is refused by the create-overtime-request tool', function () {
    $organization = mcpOvertimeOrg(OvertimeAuthorizationMode::Combined, [
        'overtime_retroactive_request_days' => 5,
    ]);
    $employee = mcpOvertimeEmployee($organization);

    KolviServer::actingAs($employee)
        ->tool(CreateOvertimeRequestTool::class, [
            'date' => now()->subDays(10)->toDateString(),
            'requested_hours' => '02:00',
        ])
        ->assertHasErrors();

    expect(OvertimeRequest::count())->toBe(0);
});

// --- view-own-overtime-requests ---

test('an employee sees only their own overtime requests', function () {
    $organization = Organization::factory()->create();
    $employee = mcpOvertimeEmployee($organization);
    $other = mcpOvertimeEmployee($organization);

    OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $employee->id]);
    OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $other->id]);

    KolviServer::actingAs($employee)
        ->tool(ViewOwnOvertimeRequestsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('requests', 1)->etc());
});

test('a user without ViewOwn:OvertimeAuthorization is denied by the view-own-overtime-requests tool', function () {
    $employee = User::factory()->create();

    KolviServer::actingAs($employee)
        ->tool(ViewOwnOvertimeRequestsTool::class)
        ->assertHasErrors();
});

// --- view-team-overtime-requests ---

test('a supervisor sees only their own team on the view-team-overtime-requests tool', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $teamMember = mcpOvertimeEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $otherEmployee = mcpOvertimeEmployee($organization);

    OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $teamMember->id]);
    OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $otherEmployee->id]);

    KolviServer::actingAs($supervisor)
        ->tool(ViewTeamOvertimeRequestsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('requests', 1)->etc());
});

test('an admin sees every team\'s overtime requests', function () {
    $admin = mcpOvertimeAdmin();
    $organization = $admin->organization;
    $employeeOne = mcpOvertimeEmployee($organization);
    $employeeTwo = mcpOvertimeEmployee($organization);

    OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $employeeOne->id]);
    OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $employeeTwo->id]);

    KolviServer::actingAs($admin)
        ->tool(ViewTeamOvertimeRequestsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('requests', 2)->etc());
});

test('the view-team-overtime-requests tool defaults to pending requests only', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $supervisor->id]);

    $pending = OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $employee->id]);
    OvertimeRequest::factory()->rejected($supervisor)->create(['organization_id' => $organization->id, 'user_id' => $employee->id]);

    KolviServer::actingAs($supervisor)
        ->tool(ViewTeamOvertimeRequestsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('requests', 1)->where('requests.0.id', $pending->id)->etc());

    KolviServer::actingAs($supervisor)
        ->tool(ViewTeamOvertimeRequestsTool::class, ['status' => 'all'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('requests', 2)->etc());
});

test('a user without ViewTeam:OvertimeAuthorization is denied by the view-team-overtime-requests tool', function () {
    $employee = mcpOvertimeEmployee();

    KolviServer::actingAs($employee)
        ->tool(ViewTeamOvertimeRequestsTool::class)
        ->assertHasErrors();
});

// --- approve-overtime-request ---

test('a supervisor can approve their own team member\'s overtime request', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $overtimeRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveOvertimeRequestTool::class, ['overtime_request_id' => $overtimeRequest->id])
        ->assertOk();

    expect($overtimeRequest->refresh()->status)->toBe(OvertimeRequestStatus::Approved)
        ->and($overtimeRequest->reviewed_by)->toBe($supervisor->id);
});

test('a supervisor cannot approve an overtime request outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $otherSupervisor = mcpOvertimeSupervisor($organization);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $otherSupervisor->id]);
    $overtimeRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveOvertimeRequestTool::class, ['overtime_request_id' => $overtimeRequest->id])
        ->assertHasErrors();

    expect($overtimeRequest->refresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('a supervisor cannot approve when the team-approval permission is revoked', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization, canApprove: false);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $overtimeRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveOvertimeRequestTool::class, ['overtime_request_id' => $overtimeRequest->id])
        ->assertHasErrors();

    expect($overtimeRequest->refresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('approving an already-decided overtime request is rejected', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $overtimeRequest = OvertimeRequest::factory()->approved($supervisor)->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveOvertimeRequestTool::class, ['overtime_request_id' => $overtimeRequest->id])
        ->assertHasErrors();
});

// --- reject-overtime-request ---

test('a supervisor can reject their own team member\'s overtime request with a reason', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $overtimeRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(RejectOvertimeRequestTool::class, [
            'overtime_request_id' => $overtimeRequest->id,
            'reason' => 'No hay presupuesto para horas extra esta semana.',
        ])
        ->assertOk();

    expect($overtimeRequest->refresh()->status)->toBe(OvertimeRequestStatus::Rejected)
        ->and($overtimeRequest->decision_reason)->toBe('No hay presupuesto para horas extra esta semana.');
});

test('the reject-overtime-request tool requires a reason', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $overtimeRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(RejectOvertimeRequestTool::class, ['overtime_request_id' => $overtimeRequest->id])
        ->assertHasErrors();

    expect($overtimeRequest->refresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('a supervisor cannot reject an overtime request outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpOvertimeSupervisor($organization);
    $otherSupervisor = mcpOvertimeSupervisor($organization);
    $employee = mcpOvertimeEmployee($organization, ['supervisor_id' => $otherSupervisor->id]);
    $overtimeRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(RejectOvertimeRequestTool::class, [
            'overtime_request_id' => $overtimeRequest->id,
            'reason' => 'No procede.',
        ])
        ->assertHasErrors();

    expect($overtimeRequest->refresh()->status)->toBe(OvertimeRequestStatus::Pending);
});
