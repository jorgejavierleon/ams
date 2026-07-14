<?php

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Managers\MarkModificationManager;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use App\Notifications\MarkModificationRequested;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function workdayAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function makeWorkday(Organization $organization, User $employee, array $attributes = []): Workday
{
    return Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        ...$attributes,
    ]);
}

// --- Access control ---

test('unauthenticated users cannot list workdays', function () {
    $this->get(route('workdays.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $this->actingAs($employee)
        ->get(route('workdays.index'))
        ->assertForbidden();
});

// --- Index ---

test('admin sees the workdays list scoped to their organization', function () {
    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);

    makeWorkday($organization, $employee, ['date' => Carbon::today()]);

    // A workday in another organization must not leak in.
    $otherOrg = Organization::factory()->create();
    $otherEmployee = User::factory()->employee()->create(['organization_id' => $otherOrg->id]);
    makeWorkday($otherOrg, $otherEmployee, ['date' => Carbon::today()]);

    $this->actingAs($admin)
        ->get(route('workdays.index'))
        ->assertInertia(fn ($page) => $page
            ->component('workdays/index')
            ->has('workdays.data', 1)
            ->where('workdays.data.0.employee', $employee->name));
});

test('the list defaults to today', function () {
    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);

    makeWorkday($organization, $employee, ['date' => Carbon::today()]);
    makeWorkday($organization, $employee, ['date' => Carbon::today()->subDays(3)]);

    $this->actingAs($admin)
        ->get(route('workdays.index'))
        ->assertInertia(fn ($page) => $page
            ->has('workdays.data', 1)
            ->where('workdays.data.0.date', Carbon::today()->format('Y-m-d'))
            ->where('filters.from', Carbon::today()->format('Y-m-d'))
            ->where('filters.to', Carbon::today()->format('Y-m-d')));
});

test('the status filter narrows the list', function () {
    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $other = User::factory()->employee()->create(['organization_id' => $organization->id]);

    makeWorkday($organization, $employee, ['date' => Carbon::today(), 'status' => WorkdayStatus::Regular]);
    makeWorkday($organization, $other, ['date' => Carbon::today(), 'status' => WorkdayStatus::Absent]);

    $this->actingAs($admin)
        ->get(route('workdays.index', ['statuses' => ['absent']]))
        ->assertInertia(fn ($page) => $page
            ->has('workdays.data', 1)
            ->where('workdays.data.0.status', 'absent'));
});

test('the date range filter narrows the list', function () {
    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $mid = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $out = User::factory()->employee()->create(['organization_id' => $organization->id]);

    makeWorkday($organization, $employee, ['date' => Carbon::parse('2026-06-10')]);
    makeWorkday($organization, $mid, ['date' => Carbon::parse('2026-06-20')]);
    makeWorkday($organization, $out, ['date' => Carbon::parse('2026-07-01')]);

    $this->actingAs($admin)
        ->get(route('workdays.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page->has('workdays.data', 2));
});

test('pending mark modifications surface as an indicator on the row', function () {
    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $other = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $withPending = makeWorkday($organization, $employee, ['date' => Carbon::today()]);
    $withoutPending = makeWorkday($organization, $other, ['date' => Carbon::today()]);

    MarkModification::factory()->create([
        'organization_id' => $organization->id,
        'workday_id' => $withPending->id,
        'user_id' => $employee->id,
        'status' => MarkModificationStatus::Pending,
    ]);
    // An already-approved modification must not count towards the indicator.
    MarkModification::factory()->approved()->create([
        'organization_id' => $organization->id,
        'workday_id' => $withoutPending->id,
        'user_id' => $withoutPending->user_id,
    ]);

    $this->actingAs($admin)
        ->get(route('workdays.index'))
        ->assertInertia(fn ($page) => $page
            ->has('workdays.data', 2)
            ->where('workdays.data', fn ($rows) => collect($rows)->firstWhere('id', $withPending->id)['pending_modifications'] === 1
                && collect($rows)->firstWhere('id', $withoutPending->id)['pending_modifications'] === 0));
});

// --- Bulk modify ---

test('bulk modify opens a pending mark modification for each selected workday', function () {
    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $other = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $first = makeWorkday($organization, $employee, ['date' => Carbon::today()]);
    $second = makeWorkday($organization, $other, ['date' => Carbon::today()]);

    $this->actingAs($admin)
        ->post(route('workdays.bulk-modify'), [
            'workdays' => [$first->id, $second->id],
            'mark_type' => MarkType::In->value,
            'time' => '08:15',
            'reason' => 'mark_forgotten',
            'notes' => 'Corrección masiva',
        ])
        ->assertRedirect();

    expect(MarkModification::query()->where('status', MarkModificationStatus::Pending)->count())->toBe(2);
    expect(MarkModification::query()->where('workday_id', $first->id)->value('created_by'))->toBe($admin->id);
});

test('bulk modify cannot target workdays from another organization', function () {
    $admin = workdayAdmin();

    $otherOrg = Organization::factory()->create();
    $otherEmployee = User::factory()->employee()->create(['organization_id' => $otherOrg->id]);
    $foreignWorkday = makeWorkday($otherOrg, $otherEmployee, ['date' => Carbon::today()]);

    $this->actingAs($admin)
        ->post(route('workdays.bulk-modify'), [
            'workdays' => [$foreignWorkday->id],
            'mark_type' => MarkType::In->value,
            'time' => '08:15',
            'reason' => 'mark_forgotten',
        ])
        ->assertSessionHasErrors('workdays.0');

    expect(MarkModification::query()->count())->toBe(0);
});

// --- Single workday modify ---

test('admin requests a mark modification and the employee is notified', function () {
    Notification::fake();

    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $workday = makeWorkday($organization, $employee, ['date' => Carbon::today()]);

    $this->actingAs($admin)
        ->post(route('workdays.modify', $workday), [
            'mark_in' => '08:05',
            'reason' => MarkModificationReason::MarkForgotten->value,
            'notes' => 'Se olvidó de marcar',
        ])
        ->assertRedirect();

    $modification = MarkModification::query()->sole();
    expect($modification->status)->toBe(MarkModificationStatus::Pending)
        ->and($modification->mark_type)->toBe(MarkType::In)
        ->and($modification->created_by)->toBe($admin->id)
        ->and($modification->user_id)->toBe($employee->id)
        ->and($modification->date_time->format('Y-m-d H:i'))->toBe(Carbon::today()->format('Y-m-d').' 08:05');

    Notification::assertSentTo($employee, MarkModificationRequested::class);
});

test('modify can target both the entry and exit marks at once', function () {
    Notification::fake();

    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $workday = makeWorkday($organization, $employee, ['date' => Carbon::today()]);

    $this->actingAs($admin)
        ->post(route('workdays.modify', $workday), [
            'mark_in' => '08:00',
            'mark_out' => '17:00',
            'reason' => MarkModificationReason::MarkIncorrect->value,
        ])
        ->assertRedirect();

    expect($workday->markModifications()->count())->toBe(2);
    Notification::assertSentToTimes($employee, MarkModificationRequested::class, 2);
});

test('a pending modification blocks a second request for the same mark', function () {
    Notification::fake();

    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $workday = makeWorkday($organization, $employee, ['date' => Carbon::today()]);

    MarkModification::factory()->create([
        'organization_id' => $organization->id,
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
        'mark_type' => MarkType::In,
        'status' => MarkModificationStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->post(route('workdays.modify', $workday), [
            'mark_in' => '08:10',
            'reason' => MarkModificationReason::MarkForgotten->value,
        ])
        ->assertRedirect();

    // The guard leaves the single pre-existing request in place.
    expect($workday->markModifications()->where('mark_type', MarkType::In)->count())->toBe(1);
    Notification::assertNothingSent();
});

test('submitting the marks unchanged requests no modification', function () {
    Notification::fake();

    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $workday = makeWorkday($organization, $employee, [
        'date' => Carbon::today(),
        'mark_in_at' => Carbon::today()->setTime(8, 0),
        'mark_out_at' => Carbon::today()->setTime(17, 0),
    ]);

    $this->actingAs($admin)
        ->post(route('workdays.modify', $workday), [
            'mark_in' => '08:00',
            'mark_out' => '17:00',
            'reason' => MarkModificationReason::MarkForgotten->value,
        ])
        ->assertRedirect();

    expect(MarkModification::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('only the mark whose time changed opens a request', function () {
    Notification::fake();

    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $workday = makeWorkday($organization, $employee, [
        'date' => Carbon::today(),
        'mark_in_at' => Carbon::today()->setTime(8, 0),
        'mark_out_at' => Carbon::today()->setTime(17, 0),
    ]);

    $this->actingAs($admin)
        ->post(route('workdays.modify', $workday), [
            'mark_in' => '08:00',  // unchanged — must not create a request
            'mark_out' => '17:30', // changed
            'reason' => MarkModificationReason::MarkIncorrect->value,
        ])
        ->assertRedirect();

    $modifications = $workday->markModifications()->get();
    expect($modifications)->toHaveCount(1)
        ->and($modifications->first()->mark_type)->toBe(MarkType::Out)
        ->and($modifications->first()->date_time->format('H:i'))->toBe('17:30');

    Notification::assertSentToTimes($employee, MarkModificationRequested::class, 1);
});

test('adding a time to a missing mark opens a request', function () {
    Notification::fake();

    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $workday = makeWorkday($organization, $employee, [
        'date' => Carbon::today(),
        'mark_in_at' => Carbon::today()->setTime(8, 0),
        'mark_out_at' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('workdays.modify', $workday), [
            'mark_in' => '08:00',  // unchanged
            'mark_out' => '17:00', // added to the missing mark
            'reason' => MarkModificationReason::MarkForgotten->value,
        ])
        ->assertRedirect();

    $modifications = $workday->markModifications()->get();
    expect($modifications)->toHaveCount(1)
        ->and($modifications->first()->mark_type)->toBe(MarkType::Out);
});

test('modify cannot target a workday from another organization', function () {
    $admin = workdayAdmin();

    $otherOrg = Organization::factory()->create();
    $otherEmployee = User::factory()->employee()->create(['organization_id' => $otherOrg->id]);
    $foreignWorkday = makeWorkday($otherOrg, $otherEmployee, ['date' => Carbon::today()]);

    $this->actingAs($admin)
        ->post(route('workdays.modify', $foreignWorkday), [
            'mark_in' => '08:00',
            'reason' => MarkModificationReason::MarkForgotten->value,
        ])
        ->assertNotFound();

    expect(MarkModification::query()->count())->toBe(0);
});

test('modify delegates to the MarkModificationManager', function () {
    $admin = workdayAdmin();
    $organization = $admin->organization;
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $workday = makeWorkday($organization, $employee, ['date' => Carbon::today()]);

    $this->mock(MarkModificationManager::class)
        ->shouldReceive('modifyFromWorkday')
        ->once()
        ->andReturn(new Collection([new MarkModification]));

    $this->actingAs($admin)
        ->post(route('workdays.modify', $workday), [
            'mark_in' => '08:00',
            'reason' => MarkModificationReason::MarkForgotten->value,
        ])
        ->assertRedirect();
});
