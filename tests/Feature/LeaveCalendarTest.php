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
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

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
