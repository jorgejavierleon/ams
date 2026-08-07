<?php

use App\Exceptions\LegalHourLimitIsAppendOnly;
use App\Http\Controllers\Saas\LegalHourLimitController;
use App\Models\LegalHourLimit;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use App\Services\LegalHourLimits;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses()->group('saas');

function saasLimitsAdmin(): User
{
    return User::factory()->saasUser()->create();
}

/**
 * A tenant administrator: the user who has every right inside their own
 * organization and none at all over the legal limits.
 */
function tenantAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * The figures a version is submitted with, overridable field by field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function versionPayload(array $overrides = []): array
{
    return [
        'effective_from' => '2030-01-01',
        'ordinary_weekly_hours' => 38,
        'ordinary_daily_hours' => 9,
        'max_overtime_daily_hours' => 2,
        'max_overtime_weekly_hours' => 12,
        'max_total_daily_hours' => 12,
        'max_total_weekly_hours' => 50,
        'legal_reference' => 'Ley 22.000',
        'notes' => 'Cuarta etapa.',
        'acknowledged_global_effect' => true,
        ...$overrides,
    ];
}

/**
 * A calculated day judged against the given version, which is what makes that
 * version used and therefore no longer editable in place.
 */
function dayCalculatedWith(LegalHourLimit $version): Workday
{
    $organization = Organization::factory()->create();

    return Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => User::factory()->employee()->create(['organization_id' => $organization->id]),
        'date' => $version->effective_from->copy()->addDay()->toDateString(),
        'legal_hour_limit_id' => $version->id,
    ]);
}

// --- Access control ---

test('unauthenticated users are redirected to the saas login', function (string $name) {
    $version = LegalHourLimit::query()->chronological()->firstOrFail();

    $this->get(route($name, $name === 'saas.legal-hour-limits.correct' ? $version : []))
        ->assertRedirect('/saas/login');
})->with([
    'saas.legal-hour-limits.index',
    'saas.legal-hour-limits.create',
    'saas.legal-hour-limits.correct',
]);

test('a saas user without the saas role is denied the whole screen', function () {
    $user = User::factory()->create();
    $version = LegalHourLimit::query()->chronological()->firstOrFail();

    $this->actingAs($user, 'saas')->get(route('saas.legal-hour-limits.index'))->assertForbidden();
    $this->actingAs($user, 'saas')->get(route('saas.legal-hour-limits.create'))->assertForbidden();
    $this->actingAs($user, 'saas')->post(route('saas.legal-hour-limits.store'), versionPayload())->assertForbidden();
    $this->actingAs($user, 'saas')->get(route('saas.legal-hour-limits.correct', $version))->assertForbidden();
    $this->actingAs($user, 'saas')
        ->put(route('saas.legal-hour-limits.update', $version), versionPayload(['reason' => 'x']))
        ->assertForbidden();

    expect(LegalHourLimit::query()->count())->toBe(4);
});

test('a tenant admin cannot create, correct or delete a legal limit', function () {
    $admin = tenantAdmin();
    $version = LegalHourLimit::query()->chronological()->firstOrFail();
    $before = $version->ordinary_weekly_hours;

    // Authenticated in their own panel, the tenant admin is simply not a user
    // of the saas guard, so the panel does not admit them at all.
    $this->actingAs($admin)
        ->post(route('saas.legal-hour-limits.store'), versionPayload())
        ->assertRedirect('/saas/login');

    $this->actingAs($admin)
        ->put(route('saas.legal-hour-limits.update', $version), versionPayload(['reason' => 'Rebajar el tope.']))
        ->assertRedirect('/saas/login');

    expect(LegalHourLimit::query()->count())->toBe(4)
        ->and($version->fresh()->ordinary_weekly_hours)->toBe($before);
});

test('every route reaching the legal limits is behind the saas role, and none deletes', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with(
            (string) ($route->getAction('controller') ?? ''),
            LegalHourLimitController::class,
        ));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->getName())->toStartWith('saas.legal-hour-limits.')
            ->and($route->gatherMiddleware())->toContain('auth:saas', 'role:saas,saas');
    }

    // Deletion has no route because it has no meaning: a day judged against a
    // version needs that version readable to stay explicable.
    expect(Route::has('saas.legal-hour-limits.destroy'))->toBeFalse();
});

test('the model refuses a deletion even when one is attempted directly', function () {
    LegalHourLimit::query()->chronological()->firstOrFail()->delete();
})->throws(LegalHourLimitIsAppendOnly::class);

// --- Timeline ---

test('the timeline shows what is in force, what it replaced and what is scheduled', function () {
    Carbon::setTestNow('2026-08-06');

    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->get(route('saas.legal-hour-limits.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('saas/legal-hour-limits/index')
            ->has('versions.data', 4)
            // Newest first: the 2028 step is scheduled, the 42-hour step is
            // what August 2026 is measured against, and the rest are history.
            ->where('versions.data.0.effective_from', '2028-04-26')
            ->where('versions.data.0.status', 'scheduled')
            ->where('versions.data.0.effective_until', null)
            ->where('versions.data.1.effective_from', '2026-04-26')
            ->where('versions.data.1.status', 'in_force')
            ->where('versions.data.1.effective_until', '2028-04-25')
            ->where('versions.data.1.ordinary_weekly_hours', 42)
            ->where('versions.data.2.status', 'superseded')
            ->where('versions.data.3.status', 'superseded')
            ->where('today', '2026-08-06')
        );
});

test('the timeline reports how many calculated days each version was applied to', function () {
    $version = LegalHourLimit::query()->chronological()->firstOrFail();
    dayCalculatedWith($version);

    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->get(route('saas.legal-hour-limits.index', ['sort' => 'effective_from', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('versions.data.0.effective_from', $version->effective_from->toDateString())
            ->where('versions.data.0.calculated_days', 1)
            ->where('versions.data.1.calculated_days', 0)
        );
});

// --- Creating a version ---

test('a saas user appends a version and it applies from its effective date', function () {
    $staff = saasLimitsAdmin();

    $this->actingAs($staff, 'saas')
        ->post(route('saas.legal-hour-limits.store'), versionPayload())
        ->assertRedirect(route('saas.legal-hour-limits.index'));

    $this->assertDatabaseHas('legal_hour_limits', [
        'effective_from' => '2030-01-01',
        'ordinary_weekly_hours' => 38,
        'legal_reference' => 'Ley 22.000',
    ]);

    $limits = app(LegalHourLimits::class);

    expect($limits->on(Carbon::parse('2030-01-01'))->ordinary_weekly_hours)->toBe(38.0)
        // Appending never moves what an earlier date resolves to.
        ->and($limits->on(Carbon::parse('2029-12-31'))->ordinary_weekly_hours)->toBe(40.0);
});

test('a version scheduled for the future does not change what today resolves to', function () {
    Carbon::setTestNow('2026-08-06');
    $staff = saasLimitsAdmin();

    $this->actingAs($staff, 'saas')
        ->post(route('saas.legal-hour-limits.store'), versionPayload(['effective_from' => '2031-05-01']))
        ->assertRedirect(route('saas.legal-hour-limits.index'));

    expect(app(LegalHourLimits::class)->on(Carbon::parse('2026-08-06'))->ordinary_weekly_hours)->toBe(42.0);

    $this->actingAs($staff, 'saas')
        ->get(route('saas.legal-hour-limits.index'))
        ->assertInertia(fn ($page) => $page
            ->where('versions.data.0.effective_from', '2031-05-01')
            ->where('versions.data.0.status', 'scheduled')
        );
});

test('the global effect has to be acknowledged before the version is saved', function () {
    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->post(route('saas.legal-hour-limits.store'), versionPayload(['acknowledged_global_effect' => false]))
        ->assertSessionHasErrors('acknowledged_global_effect');

    $this->assertDatabaseMissing('legal_hour_limits', ['effective_from' => '2030-01-01']);
});

test('two versions cannot take effect on the same date', function () {
    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->post(route('saas.legal-hour-limits.store'), versionPayload(['effective_from' => '2026-04-26']))
        ->assertSessionHasErrors('effective_from');

    expect(LegalHourLimit::query()->count())->toBe(4);
});

test('a total ceiling below the ordinary jornada is rejected', function () {
    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->post(route('saas.legal-hour-limits.store'), versionPayload([
            'ordinary_weekly_hours' => 45,
            'max_total_weekly_hours' => 40,
        ]))
        ->assertSessionHasErrors('max_total_weekly_hours');
});

test('appending a version is recorded in the audit log against the staff user', function () {
    $staff = saasLimitsAdmin();

    $this->actingAs($staff, 'saas')->post(route('saas.legal-hour-limits.store'), versionPayload());

    $entry = Activity::query()->latest('id')->firstOrFail();

    expect($entry->event)->toBe('created')
        ->and($entry->causer_id)->toBe($staff->id)
        ->and($entry->subject_type)->toBe(LegalHourLimit::class)
        ->and($entry->properties['attributes']['effective_from'])->toBe('2030-01-01')
        ->and($entry->properties['attributes']['ordinary_weekly_hours'])->toEqual(38.0)
        ->and($entry->created_at)->not->toBeNull();

    // And it is readable from the SaaS audit log screen.
    $this->actingAs($staff, 'saas')
        ->get(route('saas.audit-log.index'))
        ->assertInertia(fn ($page) => $page
            ->where('activities.data.0.subject_type', 'LegalHourLimit')
            ->where('activities.data.0.event', 'created')
            ->where('activities.data.0.causer.name', $staff->name)
        );
});

// --- Correcting a used version ---

test('the correction screen states how many calculated days it will recalculate', function () {
    $version = LegalHourLimit::query()->chronological()->firstOrFail();
    dayCalculatedWith($version);

    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->get(route('saas.legal-hour-limits.correct', $version))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('saas/legal-hour-limits/correct')
            ->where('version.id', $version->id)
            ->where('version.calculated_days', 1)
            ->where('version.ordinary_weekly_hours', 45)
        );
});

test('a version already used by a calculated day cannot be edited without the correction flow', function () {
    $version = LegalHourLimit::query()->chronological()->firstOrFail();
    dayCalculatedWith($version);

    // The same figures as the correction screen submits, minus the written
    // reason: an in-place edit, which is exactly what is refused.
    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->put(route('saas.legal-hour-limits.update', $version), versionPayload([
            'effective_from' => $version->effective_from->toDateString(),
            'ordinary_weekly_hours' => 20,
        ]))
        ->assertSessionHasErrors('reason');

    expect($version->fresh()->ordinary_weekly_hours)->toBe(45.0)
        ->and(Activity::query()->where('event', 'corrected')->count())->toBe(0);
});

test('a correction with a written reason is applied, recalculates and is audited', function () {
    $staff = saasLimitsAdmin();
    $version = LegalHourLimit::query()->chronological()->firstOrFail();
    dayCalculatedWith($version);

    $this->actingAs($staff, 'saas')
        ->put(route('saas.legal-hour-limits.update', $version), versionPayload([
            'effective_from' => $version->effective_from->toDateString(),
            'ordinary_weekly_hours' => 46,
            'ordinary_daily_hours' => 10,
            'max_overtime_daily_hours' => 2,
            'max_overtime_weekly_hours' => 12,
            'max_total_daily_hours' => 12,
            'max_total_weekly_hours' => 58,
            'legal_reference' => 'Ley 19.759',
            'notes' => $version->notes,
            'reason' => 'La cifra se transcribió desde el artículo equivocado.',
        ]))
        ->assertRedirect(route('saas.legal-hour-limits.index'));

    $entry = Activity::query()->where('event', 'corrected')->latest('id')->firstOrFail();

    expect($version->fresh()->ordinary_weekly_hours)->toBe(46.0)
        ->and($entry->causer_id)->toBe($staff->id)
        ->and($entry->properties['reason'])->toBe('La cifra se transcribió desde el artículo equivocado.')
        ->and($entry->properties['old']['ordinary_weekly_hours'])->toEqual(45.0)
        ->and($entry->properties['attributes']['ordinary_weekly_hours'])->toEqual(46.0)
        ->and($entry->properties['recalculated_workdays'])->toBe(1);
});

test('a correction that changes nothing is rejected rather than logged', function () {
    $version = LegalHourLimit::query()->chronological()->firstOrFail();

    $this->actingAs(saasLimitsAdmin(), 'saas')
        ->from(route('saas.legal-hour-limits.correct', $version))
        ->put(route('saas.legal-hour-limits.update', $version), versionPayload([
            'effective_from' => $version->effective_from->toDateString(),
            'ordinary_weekly_hours' => $version->ordinary_weekly_hours,
            'ordinary_daily_hours' => $version->ordinary_daily_hours,
            'max_overtime_daily_hours' => $version->max_overtime_daily_hours,
            'max_overtime_weekly_hours' => $version->max_overtime_weekly_hours,
            'max_total_daily_hours' => $version->max_total_daily_hours,
            'max_total_weekly_hours' => $version->max_total_weekly_hours,
            'legal_reference' => $version->legal_reference,
            'notes' => $version->notes,
            'reason' => 'Sin cambios.',
        ]))
        ->assertSessionHasErrors('reason');

    expect(Activity::query()->where('event', 'corrected')->count())->toBe(0);
});
