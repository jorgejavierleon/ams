<?php

use App\Enums\MarkType;
use App\Mail\MarkCreated;
use App\Managers\MarkManager;
use App\Models\Company;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seeds the roles and grants the self-service permissions to `employee`.
    $this->seed(RoleSeeder::class);
    Mail::fake();
});

function clockingEmployee(?Organization $organization = null, array $attributes = []): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]);
}

// --- Access control ---

test('guests cannot register a mark', function () {
    $this->post(route('my.marks.store'), ['type' => 'in'])
        ->assertRedirect(route('login'));

    expect(Mark::count())->toBe(0);
});

test('a user without the clock permission is forbidden', function () {
    $user = User::factory()->create(); // no roles, so no permissions

    $this->actingAs($user)
        ->post(route('my.marks.store'), ['type' => 'in'])
        ->assertForbidden();

    expect(Mark::count())->toBe(0);
});

test('the clock permissions are granted to the employee role', function () {
    expect(Permission::whereIn('name', ['ClockOwn:Mark', 'ViewOwn:Mark'])->count())->toBe(2);
});

// --- Registering marks ---

test('an employee can register an entry mark for themselves', function () {
    $employee = clockingEmployee();

    $this->actingAs($employee)
        ->post(route('my.marks.store'), ['type' => 'in'])
        ->assertRedirect();

    $mark = Mark::first();

    expect($mark)->not->toBeNull()
        ->and($mark->user_id)->toBe($employee->id)
        ->and($mark->type)->toBe(MarkType::In)
        ->and($mark->organization_id)->toBe($employee->organization_id)
        ->and($mark->checksum)->not->toBeEmpty();
});

test('an employee can register an exit mark after entering', function () {
    $employee = clockingEmployee();

    $this->actingAs($employee)->post(route('my.marks.store'), ['type' => 'in']);
    $this->actingAs($employee)->post(route('my.marks.store'), ['type' => 'out']);

    expect(Mark::where('type', MarkType::In)->count())->toBe(1)
        ->and(Mark::where('type', MarkType::Out)->count())->toBe(1);
});

test('a second entry mark on the same day is rejected', function () {
    $employee = clockingEmployee();

    $this->actingAs($employee)->post(route('my.marks.store'), ['type' => 'in']);
    $this->actingAs($employee)->post(route('my.marks.store'), ['type' => 'in']);

    expect(Mark::where('user_id', $employee->id)->where('type', MarkType::In)->count())->toBe(1);
});

test('the type is required and must be a valid mark type', function () {
    $employee = clockingEmployee();

    $this->actingAs($employee)
        ->post(route('my.marks.store'), ['type' => 'sideways'])
        ->assertSessionHasErrors('type');

    expect(Mark::count())->toBe(0);
});

// --- Legal snapshot & integrity ---

test('a mark captures the immutable legal snapshot from the employee and company', function () {
    $organization = Organization::factory()->create();
    $company = Company::factory()->create([
        'organization_id' => $organization->id,
        'rut' => '76.123.456-7',
        'social_reason' => 'Acme SpA',
    ]);
    $employee = clockingEmployee($organization, [
        'company_id' => $company->id,
        'name' => 'Jane Worker',
        'rut' => '11.111.111-1',
    ]);

    $this->actingAs($employee)->post(route('my.marks.store'), ['type' => 'in']);

    $mark = Mark::first();

    // RUTs are normalized by the models' FormatedRut cast; the snapshot copies
    // whatever the source records hold.
    expect($mark->employee_name)->toBe('Jane Worker')
        ->and($mark->employee_rut)->toBe($employee->rut)
        ->and($mark->employer_name)->toBe('Acme SpA')
        ->and($mark->employer_rut)->toBe($company->rut)
        ->and($mark->checksum)->toHaveLength(64); // SHA-256 hex digest
});

test('a mark is stamped in the employee timezone', function () {
    // 23:30 UTC is 19:30 the same day in Santiago (UTC-4 in July).
    $this->travelTo(Carbon::create(2026, 7, 13, 23, 30, 0, 'UTC'));

    $employee = clockingEmployee(null, ['timezone' => 'America/Santiago']);

    $mark = app(MarkManager::class)->createMark(MarkType::In, $employee);

    expect($mark->date_time->format('H:i'))->toBe('19:30');
});

test('registering a mark emails the employee a receipt', function () {
    $employee = clockingEmployee(null, ['personal_email' => 'jane@example.com']);

    $this->actingAs($employee)->post(route('my.marks.store'), ['type' => 'in']);

    Mail::assertQueued(MarkCreated::class, fn (MarkCreated $mail) => $mail->hasTo('jane@example.com'));
});

// --- Shift snapshot ---

test('a mark records the shift scheduled for the day', function () {
    $this->travelTo(Carbon::create(2026, 7, 13, 12, 0, 0, 'America/Santiago')); // a Monday

    $organization = Organization::factory()->create();
    $employee = clockingEmployee($organization, ['timezone' => 'America/Santiago']);

    $shift = Shift::factory()->create(['organization_id' => $organization->id]);
    // ShiftObserver seeds a default day per weekday; set Monday's hours to
    // known values rather than adding a duplicate row.
    ShiftDay::updateOrCreate(
        ['shift_id' => $shift->id, 'weekday' => 0], // Monday
        ['start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_free' => false],
    );
    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($employee)->post(route('my.marks.store'), ['type' => 'in']);

    $mark = Mark::first();

    expect($mark->shift_id)->toBe($shift->id)
        ->and($mark->shift_start_time->format('H:i'))->toBe('09:00')
        ->and($mark->shift_end_time->format('H:i'))->toBe('18:00');
});

// --- Dashboard state ---

test('the dashboard exposes the clock state for an employee', function () {
    // Midday UTC keeps the mark on the same calendar day in Santiago.
    $this->travelTo(Carbon::create(2026, 7, 13, 15, 0, 0, 'UTC'));

    $employee = clockingEmployee(null, ['timezone' => 'America/Santiago']);

    Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => MarkType::In,
        'date_time' => now(),
    ]);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('clock')
            ->where('clock.in', now()->format('H:i'))
            ->where('clock.out', null));
});

test('the dashboard has no clock state for a user who cannot clock in', function () {
    $admin = User::factory()->create(); // no clock permission

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('clock', null));
});

test('the clock permissions are granted to the admin role', function () {
    expect(Role::findByName('admin')->hasAllPermissions(['ClockOwn:Mark', 'ViewOwn:Mark']))->toBeTrue();
});

test('an admin gets the clock widget on the dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('clock'));
});

test('an admin can register a mark for themselves', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('my.marks.store'), ['type' => 'in'])
        ->assertRedirect();

    $mark = Mark::first();

    expect($mark)->not->toBeNull()
        ->and($mark->user_id)->toBe($admin->id)
        ->and($mark->type)->toBe(MarkType::In);
});
