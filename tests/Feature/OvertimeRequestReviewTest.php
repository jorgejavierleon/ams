<?php

use App\Enums\OvertimeAuthorizationMode;
use App\Enums\OvertimeRequestStatus;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * HTTP-level tests for KOL-72's standalone Solicitudes screen — Mode A
 * requests extracted from the old /overtime/queue into their own route,
 * independent of Jornadas and of the queue.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function otReqSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function otReqEmployee(Organization $organization, ?User $supervisor = null): User
{
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
    $employee->assignRole('employee');

    return $employee;
}

test('a supervisor lists their team\'s pending requests on the standalone screen', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
    $supervisor = otReqSupervisor($organization);
    $employee = otReqEmployee($organization, $supervisor);
    $otherSupervisor = otReqSupervisor($organization);
    $otherEmployee = otReqEmployee($organization, $otherSupervisor);

    $ownRequest = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $otherEmployee->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('overtime.requests.index'))
        ->assertInertia(fn ($page) => $page
            ->component('overtime/requests/index')
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $ownRequest->id)
            ->where('can.decide', true));
});

test('an admin lists every team\'s requests', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
    $admin = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $supervisor = otReqSupervisor($organization);
    $employee = otReqEmployee($organization, $supervisor);

    OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($admin)
        ->get(route('overtime.requests.index'))
        ->assertInertia(fn ($page) => $page->has('requests.data', 1));
});

test('the standalone screen 404s under pure post-hoc mode', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PostHoc,
    ]);
    $supervisor = otReqSupervisor($organization);

    $this->actingAs($supervisor)
        ->get(route('overtime.requests.index'))
        ->assertNotFound();
});

test('a supervisor approves a direct report\'s request; approving alone never creates a payable hour', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otReqSupervisor($organization);
    $employee = otReqEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.requests.approve', $request))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Approved)
        ->and($request->fresh()->reviewed_by)->toBe($supervisor->id)
        ->and(OvertimeAuthorization::withoutGlobalScopes()->count())->toBe(0);
});

test('a supervisor rejects a request with a reason; the employee is not blocked from working', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otReqSupervisor($organization);
    $employee = otReqEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.requests.reject', $request), [
            'reason' => 'No hay presupuesto para horas extra esta semana.',
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Rejected)
        ->and($request->fresh()->decision_reason)->toBe('No hay presupuesto para horas extra esta semana.');

    expect(OvertimeAuthorization::withoutGlobalScopes()->count())->toBe(0);
});

test('a rejection without a reason is refused', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otReqSupervisor($organization);
    $employee = otReqEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.requests.reject', $request))
        ->assertSessionHasErrors('reason');

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('a supervisor cannot decide a request from outside their team', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otReqSupervisor($organization);
    $otherSupervisor = otReqSupervisor($organization);
    $employee = otReqEmployee($organization, $otherSupervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.requests.approve', $request))
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('a plain employee without ViewTeam or Manage cannot reach the screen', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
    $employee = otReqEmployee($organization);

    $this->actingAs($employee)
        ->get(route('overtime.requests.index'))
        ->assertForbidden();
});

test('a decided request is hidden from the default view but reachable through its own status filter', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
    $supervisor = otReqSupervisor($organization);
    $employee = otReqEmployee($organization, $supervisor);

    $pending = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    $rejected = OvertimeRequest::factory()->rejected($supervisor)->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('overtime.requests.index'))
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $pending->id));

    $this->actingAs($supervisor)
        ->get(route('overtime.requests.index', ['status' => 'rejected']))
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $rejected->id));

    $this->actingAs($supervisor)
        ->get(route('overtime.requests.index', ['status' => 'all']))
        ->assertInertia(fn ($page) => $page->has('requests.data', 2));
});
