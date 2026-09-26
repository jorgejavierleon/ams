<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Mcp\Servers\KolviServer;
use App\Mcp\Tools\Leave\ApproveLeaveTool;
use App\Mcp\Tools\Leave\CancelLeaveTool;
use App\Mcp\Tools\Leave\CreateLeaveForEmployeeTool;
use App\Mcp\Tools\Leave\CreateLeaveTool;
use App\Mcp\Tools\Leave\RejectLeaveTool;
use App\Mcp\Tools\Leave\ViewOwnLeavesTool;
use App\Mcp\Tools\Leave\ViewTeamLeavesTool;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('mcp');

beforeEach(function () {
    // Seeds every role + permission, including the new Create:Leave (KOL-127).
    $this->seed(RoleSeeder::class);
});

function mcpLeaveEmployee(?Organization $organization = null, array $attributes = []): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]);
}

function mcpLeaveAdmin(?Organization $organization = null): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Owner (KOL-133): admin alone no longer bypasses LeavePolicy's viewTeam,
    // so this test's "admin" is also the organization's Owner. owner_id is
    // deliberately not fillable outside TransferOrganizationOwnership, so an
    // existing organization is force-filled instead of mass-assigned.
    if ($organization === null) {
        $organization = Organization::factory()->ownedBy($admin)->create();
    } elseif ($organization->owner_id === null) {
        $organization->forceFill(['owner_id' => $admin->id])->save();
    }

    $admin->update(['organization_id' => $organization->id]);

    return $admin;
}

/**
 * A supervisor employee. When $canApprove is false we mimic an admin who has
 * revoked the team-approval permission from the shared supervisor role.
 */
function mcpLeaveSupervisor(Organization $organization, bool $canApprove = true): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    if (! $canApprove) {
        Role::findByName('supervisor')->revokePermissionTo('ApproveTeam:Leave');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    return $supervisor;
}

// --- Permission seeding (AC #3) ---

test('the Create:Leave permission is seeded and granted to admin', function () {
    expect(Permission::where('name', 'Create:Leave')->exists())->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('Create:Leave'))->toBeTrue();
});

// --- create-leave (self-service) ---

test('an employee can create a leave for themselves via the create-leave tool', function () {
    $employee = mcpLeaveEmployee();

    KolviServer::actingAs($employee)
        ->tool(CreateLeaveTool::class, [
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 3,
        ])
        ->assertOk();

    $leave = Leave::first();

    expect($leave)->not->toBeNull()
        ->and($leave->user_id)->toBe($employee->id)
        ->and($leave->status)->toBe(LeaveStatus::Pending);
});

test('an employee without RequestOwn:Leave is denied by the create-leave tool', function () {
    $employee = User::factory()->create(); // no roles, so no permissions

    KolviServer::actingAs($employee)
        ->tool(CreateLeaveTool::class, [
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'business_days_requested' => 2,
        ])
        ->assertHasErrors();

    expect(Leave::count())->toBe(0);
});

test('the create-leave tool refuses a medical leave', function () {
    $employee = mcpLeaveEmployee();

    KolviServer::actingAs($employee)
        ->tool(CreateLeaveTool::class, [
            'type' => LeaveType::Medical->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'business_days_requested' => 2,
        ])
        ->assertHasErrors();

    expect(Leave::count())->toBe(0);
});

// --- view-own-leaves ---

test('an employee sees only their own leaves and vacation balance', function () {
    $organization = Organization::factory()->create();
    $employee = mcpLeaveEmployee($organization, ['vacation_days' => 10, 'additional_vacation_days' => 0]);
    $other = mcpLeaveEmployee($organization);

    Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'business_days_requested' => 2,
    ]);
    Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
    ]);

    KolviServer::actingAs($employee)
        ->tool(ViewOwnLeavesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('leaves', 1)
            ->where('vacation_balance.used', 2.0)
            ->where('vacation_balance.available', 10.0)
            ->etc());
});

test('a user without ViewOwn:Leave is denied by the view-own-leaves tool', function () {
    $employee = User::factory()->create();

    KolviServer::actingAs($employee)
        ->tool(ViewOwnLeavesTool::class)
        ->assertHasErrors();
});

// --- cancel-leave ---

test('an employee can cancel their own pending leave', function () {
    $employee = mcpLeaveEmployee();
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    KolviServer::actingAs($employee)
        ->tool(CancelLeaveTool::class, ['leave_id' => $leave->id])
        ->assertOk();

    expect(Leave::find($leave->id))->toBeNull();
});

test('an employee cannot cancel another employees leave', function () {
    $organization = Organization::factory()->create();
    $employee = mcpLeaveEmployee($organization);
    $other = mcpLeaveEmployee($organization);
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
    ]);

    KolviServer::actingAs($employee)
        ->tool(CancelLeaveTool::class, ['leave_id' => $leave->id])
        ->assertHasErrors();

    expect(Leave::find($leave->id))->not->toBeNull();
});

test('an employee cannot cancel a request that is no longer pending', function () {
    $employee = mcpLeaveEmployee();
    $leave = Leave::factory()->approved()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    KolviServer::actingAs($employee)
        ->tool(CancelLeaveTool::class, ['leave_id' => $leave->id])
        ->assertHasErrors();

    expect(Leave::find($leave->id))->not->toBeNull();
});

test('a user without CancelOwn:Leave is denied by the cancel-leave tool', function () {
    $employee = User::factory()->create();

    KolviServer::actingAs($employee)
        ->tool(CancelLeaveTool::class, ['leave_id' => 1])
        ->assertHasErrors();
});

// --- view-team-leaves ---

test('a supervisor sees only their own team on the view-team-leaves tool', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $teamMember = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $otherEmployee = mcpLeaveEmployee($organization);

    Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $teamMember->id]);
    Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $otherEmployee->id]);

    KolviServer::actingAs($supervisor)
        ->tool(ViewTeamLeavesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('leaves', 1)->etc());
});

test('an admin sees every leave on the view-team-leaves tool', function () {
    $admin = mcpLeaveAdmin();
    $organization = $admin->organization;
    $employeeOne = mcpLeaveEmployee($organization);
    $employeeTwo = mcpLeaveEmployee($organization);

    Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $employeeOne->id]);
    Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $employeeTwo->id]);

    KolviServer::actingAs($admin)
        ->tool(ViewTeamLeavesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('leaves', 2)->etc());
});

test('the organization Owner sees every leave on the view-team-leaves tool without the admin role', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);
    $employeeOne = mcpLeaveEmployee($organization);
    $employeeTwo = mcpLeaveEmployee($organization);

    Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $employeeOne->id]);
    Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $employeeTwo->id]);

    // Not scoped like a supervisor: the Owner has no direct reports at all,
    // so a bare ViewTeam:Leave-style scope would wrongly return zero here.
    KolviServer::actingAs($owner)
        ->tool(ViewTeamLeavesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('leaves', 2)->etc());
});

test('a user without ViewTeam:Leave is denied by the view-team-leaves tool', function () {
    $employee = mcpLeaveEmployee();

    KolviServer::actingAs($employee)
        ->tool(ViewTeamLeavesTool::class)
        ->assertHasErrors();
});

// --- approve-leave ---

test('a supervisor can approve their own team members leave', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $employee = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveLeaveTool::class, ['leave_id' => $leave->id])
        ->assertOk();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Approved)
        ->and($leave->approved_by)->toBe($supervisor->id);
});

test('a supervisor cannot approve a leave outside their team', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $employee = mcpLeaveEmployee($organization); // reports to nobody
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveLeaveTool::class, ['leave_id' => $leave->id])
        ->assertHasErrors();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Pending);
});

test('a supervisor cannot approve when the team-approval permission is revoked', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization, canApprove: false);
    $employee = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveLeaveTool::class, ['leave_id' => $leave->id])
        ->assertHasErrors();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Pending);
});

test('approving an already-approved leave is rejected by the approve-leave tool', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $employee = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $leave = Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(ApproveLeaveTool::class, ['leave_id' => $leave->id])
        ->assertHasErrors();
});

// --- reject-leave ---

test('a supervisor can reject their own team members leave with a reason', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $employee = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(RejectLeaveTool::class, ['leave_id' => $leave->id, 'reason' => 'Cupo mensual excedido.'])
        ->assertOk();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Rejected)
        ->and($leave->rejection_reason)->toBe('Cupo mensual excedido.');
});

test('the reject-leave tool requires a reason', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $employee = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $leave = Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Paid,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(RejectLeaveTool::class, ['leave_id' => $leave->id])
        ->assertHasErrors();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Pending);
});

test('medical leaves cannot be rejected by the reject-leave tool', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $employee = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);
    $leave = Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Medical,
    ]);

    KolviServer::actingAs($supervisor)
        ->tool(RejectLeaveTool::class, ['leave_id' => $leave->id, 'reason' => 'No procede.'])
        ->assertHasErrors();

    expect($leave->refresh()->status)->toBe(LeaveStatus::Approved);
});

// --- create-leave-for-employee (admin) ---

test('an admin can create a leave on behalf of an employee', function () {
    $admin = mcpLeaveAdmin();
    $organization = $admin->organization;
    $employee = mcpLeaveEmployee($organization);

    KolviServer::actingAs($admin)
        ->tool(CreateLeaveForEmployeeTool::class, [
            'user_id' => $employee->id,
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'half_day' => false,
            'business_days_requested' => 3,
        ])
        ->assertOk();

    $leave = Leave::first();

    expect($leave)->not->toBeNull()
        ->and($leave->user_id)->toBe($employee->id)
        ->and($leave->status)->toBe(LeaveStatus::Pending)
        ->and($leave->created_by)->toBe($admin->id);
});

test('creating a medical leave on behalf of an employee auto-approves it', function () {
    $admin = mcpLeaveAdmin();
    $organization = $admin->organization;
    $employee = mcpLeaveEmployee($organization);

    KolviServer::actingAs($admin)
        ->tool(CreateLeaveForEmployeeTool::class, [
            'user_id' => $employee->id,
            'type' => LeaveType::Medical->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'business_days_requested' => 2,
            'medical_leave_number' => '12345',
            'medical_leave_doctor' => 'Dr. House',
        ])
        ->assertOk();

    expect(Leave::first()->status)->toBe(LeaveStatus::Approved);
});

test('a non-admin without Create:Leave is denied by the create-leave-for-employee tool', function () {
    $organization = Organization::factory()->create();
    $supervisor = mcpLeaveSupervisor($organization);
    $employee = mcpLeaveEmployee($organization, ['supervisor_id' => $supervisor->id]);

    KolviServer::actingAs($supervisor)
        ->tool(CreateLeaveForEmployeeTool::class, [
            'user_id' => $employee->id,
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'business_days_requested' => 2,
        ])
        ->assertHasErrors();

    expect(Leave::count())->toBe(0);
});

test('the create-leave-for-employee tool rejects an employee from another organization', function () {
    $admin = mcpLeaveAdmin();
    $outsider = mcpLeaveEmployee(); // different organization

    KolviServer::actingAs($admin)
        ->tool(CreateLeaveForEmployeeTool::class, [
            'user_id' => $outsider->id,
            'type' => LeaveType::Vacation->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'business_days_requested' => 2,
        ])
        ->assertHasErrors();

    expect(Leave::count())->toBe(0);
});
