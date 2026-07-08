<?php

use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function shiftAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * Seven day rows: Mon–Fri working 08:00–17:00 with a 1h lunch (8h each),
 * Saturday and Sunday non-working.
 *
 * @return array<int, array<string, mixed>>
 */
function shiftDays(): array
{
    $days = [];

    for ($weekday = 0; $weekday < 7; $weekday++) {
        $days[] = [
            'weekday' => $weekday,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'is_free' => $weekday === 5 || $weekday === 6,
        ];
    }

    return $days;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shiftPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Turno Mañana',
        'type' => 'fixed',
        'description' => 'Test shift',
        'tolerance_in' => 10,
        'tolerance_out' => 10,
        'work_on_holidays' => false,
        'is_archive' => false,
        'is_default' => false,
        'days' => shiftDays(),
    ], $overrides);
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('shifts.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('shifts.index'))->assertForbidden();
});

// --- Index ---

test('admin can list shifts with weekly hours and assignment count', function () {
    $admin = shiftAdmin();
    $shift = Shift::factory()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Turno Mañana',
    ]);
    ShiftAssignment::factory()->create([
        'organization_id' => $admin->organization_id,
        'shift_id' => $shift->id,
        'user_id' => User::factory()->create(['organization_id' => $admin->organization_id])->id,
    ]);

    $this->actingAs($admin)
        ->get(route('shifts.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('shifts/index')
                ->has('shifts.data', 1)
                ->where('shifts.data.0.name', 'Turno Mañana')
                ->where('shifts.data.0.total_week_hours', 40)
                ->where('shifts.data.0.assignments_count', 1),
        );
});

test('shifts index only shows the current organization shifts', function () {
    $admin = shiftAdmin();
    Shift::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Mine']);
    Shift::factory()->create(['name' => 'Foreign']);

    $this->actingAs($admin)
        ->get(route('shifts.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('shifts.data', 1)
                ->where('shifts.data.0.name', 'Mine'),
        );
});

// --- Create ---

test('admin can create a shift with seven days and computed weekly hours', function () {
    $admin = shiftAdmin();

    $this->actingAs($admin)
        ->post(route('shifts.store'), shiftPayload())
        ->assertRedirect(route('shifts.index'));

    $shift = Shift::query()->firstOrFail();

    expect($shift->days()->count())->toBe(7);
    // 5 working days * 8h = 40h; observers rolled the total up.
    expect($shift->total_week_hours)->toBe(40.0);
    expect($shift->days()->where('weekday', 5)->first()->is_free)->toBeTrue();

    $this->assertDatabaseHas('shifts', [
        'id' => $shift->id,
        'name' => 'Turno Mañana',
        'organization_id' => $admin->organization_id,
    ]);
});

test('tolerance minutes are stored as a time and returned as minutes', function () {
    $admin = shiftAdmin();

    $this->actingAs($admin)
        ->post(route('shifts.store'), shiftPayload([
            'tolerance_in' => 30,
            'tolerance_out' => 120,
        ]))
        ->assertRedirect(route('shifts.index'));

    $shift = Shift::query()->firstOrFail();

    // Stored as a TIME the WorkdayCalculator can compare against lateness.
    $this->assertDatabaseHas('shifts', [
        'id' => $shift->id,
        'tolerance_in' => '00:30:00',
        'tolerance_out' => '02:00:00',
    ]);

    // The edit screen exposes the values back as whole minutes.
    $this->actingAs($admin)
        ->get(route('shifts.edit', $shift))
        ->assertInertia(
            fn ($page) => $page
                ->where('shift.tolerance_in', 30)
                ->where('shift.tolerance_out', 120),
        );
});

test('creating a shift rejects a non-integer tolerance', function () {
    $admin = shiftAdmin();

    $this->actingAs($admin)
        ->post(route('shifts.store'), shiftPayload(['tolerance_in' => '00:30']))
        ->assertSessionHasErrors('tolerance_in');
});

test('creating a shift validates required fields server-side', function () {
    $admin = shiftAdmin();

    $this->actingAs($admin)
        ->post(route('shifts.store'), ['days' => []])
        ->assertSessionHasErrors(['name', 'type', 'days']);
});

test('creating a shift rejects a weekly total over the legal maximum', function () {
    $admin = shiftAdmin();

    // Make every day a working day: 7 * 8h = 56h > 45h.
    $days = array_map(function (array $day): array {
        $day['is_free'] = false;

        return $day;
    }, shiftDays());

    $this->actingAs($admin)
        ->post(route('shifts.store'), shiftPayload(['days' => $days]))
        ->assertSessionHasErrors('days');

    expect(Shift::query()->count())->toBe(0);
});

test('creating a shift requires times for working days', function () {
    $admin = shiftAdmin();

    $days = shiftDays();
    $days[0]['start_time'] = '';
    $days[0]['end_time'] = '';

    $this->actingAs($admin)
        ->post(route('shifts.store'), shiftPayload(['days' => $days]))
        ->assertSessionHasErrors(['days.0.start_time', 'days.0.end_time']);
});

// --- Update ---

test('updating a day recalculates the shift weekly hours through the observers', function () {
    $admin = shiftAdmin();
    $shift = Shift::factory()->create(['organization_id' => $admin->organization_id]);

    $days = shiftDays();
    // Monday now 08:00–18:00 => 10h - 1h lunch = 9h (weekly 41h).
    $days[0]['end_time'] = '18:00';

    $this->actingAs($admin)
        ->patch(route('shifts.update', $shift), shiftPayload(['days' => $days]))
        ->assertRedirect(route('shifts.index'));

    $monday = $shift->days()->where('weekday', 0)->first();

    expect($monday->total_work_hours)->toBe(9.0);
    expect($shift->fresh()->total_week_hours)->toBe(41.0);
});

test('admin cannot edit a shift from another organization', function () {
    $admin = shiftAdmin();
    $foreign = Shift::factory()->create();

    $this->actingAs($admin)
        ->get(route('shifts.edit', $foreign))
        ->assertNotFound();
});

test('only one shift per organization stays the default', function () {
    $admin = shiftAdmin();
    $existingDefault = Shift::factory()->default()->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->actingAs($admin)
        ->post(route('shifts.store'), shiftPayload(['is_default' => true]))
        ->assertRedirect(route('shifts.index'));

    expect($existingDefault->fresh()->is_default)->toBeFalse();
    expect(Shift::query()->where('is_default', true)->count())->toBe(1);
});

// --- Delete ---

test('a shift with an active assignment cannot be deleted', function () {
    $admin = shiftAdmin();
    $shift = Shift::factory()->create(['organization_id' => $admin->organization_id]);
    ShiftAssignment::factory()->create([
        'organization_id' => $admin->organization_id,
        'shift_id' => $shift->id,
        'user_id' => User::factory()->create(['organization_id' => $admin->organization_id])->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('shifts.destroy', $shift));

    $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'deleted_at' => null]);
});

test('a shift with only ended assignments can be deleted', function () {
    $admin = shiftAdmin();
    $shift = Shift::factory()->create(['organization_id' => $admin->organization_id]);
    ShiftAssignment::factory()->ended()->create([
        'organization_id' => $admin->organization_id,
        'shift_id' => $shift->id,
        'user_id' => User::factory()->create(['organization_id' => $admin->organization_id])->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('shifts.destroy', $shift))
        ->assertRedirect(route('shifts.index'));

    $this->assertSoftDeleted('shifts', ['id' => $shift->id]);
});
