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
 * HTTP-level tests for KOL-45's supervisor decision on Mode A requests,
 * decided in the same queue as unrequested excess (KOL-44) rather than a
 * separate inbox.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function otQueueSupervisor(Organization $organization): User
{
    $supervisor = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $supervisor->assignRole('supervisor');

    return $supervisor;
}

function otQueueEmployee(Organization $organization, ?User $supervisor = null): User
{
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'supervisor_id' => $supervisor?->id,
    ]);
    $employee->assignRole('employee');

    return $employee;
}

test('a pending request appears in the same queue page as unrequested excess', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
    $supervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $supervisor);

    OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->get(route('overtime.queue.index'))
        ->assertInertia(fn ($page) => $page
            ->component('overtime/queue/index')
            ->has('requests.data', 1)
            ->where('can.requests', true));
});

test('the requests list is empty under pure post-hoc mode', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PostHoc,
    ]);
    $supervisor = otQueueSupervisor($organization);

    $this->actingAs($supervisor)
        ->get(route('overtime.queue.index'))
        ->assertInertia(fn ($page) => $page
            ->where('can.requests', false)
            ->where('requests.data', []));
});

test('a supervisor approves a direct report\'s request; approving alone never creates a payable hour', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.requests.approve', $request))
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
    $supervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.requests.reject', $request), [
            'reason' => 'No hay presupuesto para horas extra esta semana.',
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Rejected)
        ->and($request->fresh()->decision_reason)->toBe('No hay presupuesto para horas extra esta semana.');

    // A rejection is not a bar to working: nothing on the employee's account
    // is touched, and there is still no authorisation record for the day.
    expect(OvertimeAuthorization::withoutGlobalScopes()->count())->toBe(0);
});

test('a rejection without a reason is refused', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.requests.reject', $request))
        ->assertSessionHasErrors('reason');

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('a supervisor cannot decide a request from outside their team', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otQueueSupervisor($organization);
    $otherSupervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $otherSupervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.requests.approve', $request))
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Pending);
});

test('an admin may decide any team\'s request', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $admin = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $supervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($admin)
        ->post(route('overtime.queue.requests.approve', $request))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Approved);
});

test('an already-decided request cannot be decided again', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);
    $supervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $supervisor);

    $request = OvertimeRequest::factory()->approved($supervisor)->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($supervisor)
        ->post(route('overtime.queue.requests.approve', $request))
        ->assertForbidden();
});

test('a decided request is hidden from the default Solicitudes tab but reachable through its own status filter', function () {
    $organization = Organization::factory()->create();
    Setting::factory()->create([
        'organization_id' => $organization->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined,
    ]);
    $supervisor = otQueueSupervisor($organization);
    $employee = otQueueEmployee($organization, $supervisor);

    $pending = OvertimeRequest::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    $rejected = OvertimeRequest::factory()->rejected($supervisor)->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    // Default (no filter) view: pending-only, matching the tab's own badge.
    $this->actingAs($supervisor)
        ->get(route('overtime.queue.index'))
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $pending->id)
            ->where('pendingRequestsCount', 1));

    // Filtering to rejected surfaces the decided request instead.
    $this->actingAs($supervisor)
        ->get(route('overtime.queue.index', ['request_status' => 'rejected']))
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $rejected->id)
            ->where('requests.data.0.reviewed_by', $supervisor->name)
            // The badge still counts what needs a decision, not what the
            // current filter happens to show.
            ->where('pendingRequestsCount', 1));

    // "all" clears the filter and shows both.
    $this->actingAs($supervisor)
        ->get(route('overtime.queue.index', ['request_status' => 'all']))
        ->assertInertia(fn ($page) => $page->has('requests.data', 2));
});
