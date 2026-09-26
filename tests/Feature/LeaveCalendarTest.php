<?php

use App\Enums\LeaveType;
use App\Events\WorkdaysRecalculationNeeded;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    Event::fake([WorkdaysRecalculationNeeded::class]);
});

function calendarAdmin(?Organization $organization = null): User
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

function calendarEmployee(Organization $organization, array $attributes = []): User
{
    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]);
}

// --- Access control ---

test('unauthenticated users cannot fetch calendar events', function () {
    // The api/* path renders JSON, so an unauthenticated request is a 401
    // rather than a redirect to the login page.
    $this->getJson(route('leaves.calendar.events', ['start' => '2026-07-01', 'end' => '2026-08-01']))
        ->assertUnauthorized();
});

test('unauthenticated users are redirected from the calendar page', function () {
    $this->get(route('leaves.calendar'))->assertRedirect(route('login'));
});

test('employees without team review access are denied', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(calendarEmployee($organization))
        ->getJson(route('leaves.calendar.events', ['start' => '2026-07-01', 'end' => '2026-08-01']))
        ->assertForbidden();
});

test('the calendar page renders', function () {
    $this->actingAs(calendarAdmin())
        ->get(route('leaves.calendar'))
        ->assertOk();
});

// --- Events endpoint ---

test('the endpoint returns approved leaves in the range as fullcalendar events', function () {
    $admin = calendarAdmin();
    $organization = $admin->organization;
    $employee = calendarEmployee($organization, ['name' => 'Ada Lovelace']);

    $leave = Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-12',
        'approved_by' => $admin->id,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('leaves.calendar.events', ['start' => '2026-07-01', 'end' => '2026-08-01']))
        ->assertOk()
        ->assertJsonCount(1);

    $response->assertJsonFragment([
        'id' => (string) $leave->id,
        'title' => 'Ada Lovelace',
        'start' => '2026-07-10',
        // End date is exclusive in FullCalendar, so the day after end_date.
        'end' => '2026-07-13',
        'allDay' => true,
        'color' => LeaveType::Vacation->color(),
    ]);

    $response->assertJsonPath('0.extendedProps.employee', 'Ada Lovelace');
    $response->assertJsonPath('0.extendedProps.type', LeaveType::Vacation->value);
    $response->assertJsonPath('0.extendedProps.approved_by', $admin->name);
});

test('the organization Owner sees every approved leave without the admin role, not scoped to their own team', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create();
    $owner->update(['organization_id' => $organization->id]);
    $employee = calendarEmployee($organization);

    Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-12',
        'approved_by' => $owner->id,
        'created_by' => $owner->id,
    ]);

    // Not scoped like a supervisor: the Owner has no direct reports at all,
    // so a bare ViewTeam:Leave-style scope would wrongly return zero here.
    $this->actingAs($owner)
        ->getJson(route('leaves.calendar.events', ['start' => '2026-07-01', 'end' => '2026-08-01']))
        ->assertOk()
        ->assertJsonCount(1);
});

test('pending leaves and leaves outside the range are excluded', function () {
    $admin = calendarAdmin();
    $organization = $admin->organization;
    $employee = calendarEmployee($organization);

    // Pending leave inside the range.
    Leave::factory()->pending()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-12',
        'created_by' => $admin->id,
    ]);

    // Approved leave entirely before the range.
    Leave::factory()->approved()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-05',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('leaves.calendar.events', ['start' => '2026-07-01', 'end' => '2026-08-01']))
        ->assertOk()
        ->assertJsonCount(0);
});

test('leaves from another organization do not leak in', function () {
    $admin = calendarAdmin();
    $organization = $admin->organization;

    $otherOrg = Organization::factory()->create();
    $otherEmployee = calendarEmployee($otherOrg);

    Leave::factory()->approved()->create([
        'organization_id' => $otherOrg->id,
        'user_id' => $otherEmployee->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-12',
        'created_by' => $otherEmployee->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('leaves.calendar.events', ['start' => '2026-07-01', 'end' => '2026-08-01']))
        ->assertOk()
        ->assertJsonCount(0);
});

test('the endpoint validates the date range', function () {
    $this->actingAs(calendarAdmin())
        ->getJson(route('leaves.calendar.events'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start', 'end']);
});
