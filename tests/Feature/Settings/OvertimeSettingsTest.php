<?php

use App\Enums\OvertimeAuthorizationMode;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrganizationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

/**
 * An admin bound to a real organization so settings scope correctly.
 */
function overtimeSettingsAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * A complete, valid overtime policy payload. The update endpoint saves the
 * form as a whole, so every test that patches has to send every key.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function overtimeSettingsPayload(array $overrides = []): array
{
    return [
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PostHoc->value,
        'overtime_weekly_anomaly_threshold_hours' => 10,
        'overtime_retroactive_request_days' => 7,
        'overtime_counts_pre_shift_excess' => false,
        ...$overrides,
    ];
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('settings-overtime.edit'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('settings-overtime.edit'))
        ->assertForbidden();

    $this->actingAs($employee)
        ->patch(route('settings-overtime.update'), [])
        ->assertForbidden();
});

// --- Edit ---

test('a brand-new organization gets the legal overtime defaults', function () {
    $admin = overtimeSettingsAdmin();

    $this->actingAs($admin)
        ->get(route('settings-overtime.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/overtime')
            ->where('settings.overtime_authorization_mode', OvertimeAuthorizationMode::Combined->value)
            // JSON has no float/int distinction, so compare numerically.
            ->where('settings.overtime_weekly_anomaly_threshold_hours', fn ($hours) => (float) $hours === 10.0)
            ->where('settings.overtime_retroactive_request_days', 7)
            ->where('settings.overtime_counts_pre_shift_excess', false)
        );

    $setting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();

    expect($setting->overtime_authorization_mode)->toBe(OvertimeAuthorizationMode::Combined)
        ->and($setting->overtime_weekly_anomaly_threshold_hours)->toBe(10.0)
        ->and($setting->overtime_retroactive_request_days)->toBe(7);
});

test('the defaults are readable through the settings service without a query per read', function () {
    $admin = overtimeSettingsAdmin();
    $this->actingAs($admin);
    $settings = app(OrganizationSettings::class);

    // First read creates the row and warms the cache; the rest never hit the database.
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::Combined);

    DB::enableQueryLog();

    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::Combined)
        ->and($settings->get('overtime_weekly_anomaly_threshold_hours'))->toEqual(10.0)
        ->and($settings->get('overtime_retroactive_request_days'))->toEqual(7)
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

// --- Update ---

test('each authorization mode round-trips through the settings service', function (OvertimeAuthorizationMode $mode) {
    $admin = overtimeSettingsAdmin();
    $this->actingAs($admin);
    $settings = app(OrganizationSettings::class);

    // Warm the cache with the default so the update has something to invalidate.
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::Combined);

    $this->patch(route('settings-overtime.update'), overtimeSettingsPayload([
        'overtime_authorization_mode' => $mode->value,
    ]))->assertRedirect();

    expect($settings->overtimeAuthorizationMode())->toBe($mode);
})->with([
    OvertimeAuthorizationMode::PreAuthorization,
    OvertimeAuthorizationMode::PostHoc,
    OvertimeAuthorizationMode::Combined,
]);

test('the whole overtime policy persists and is read back typed', function () {
    $admin = overtimeSettingsAdmin();
    $this->actingAs($admin);
    $settings = app(OrganizationSettings::class);

    $this->patch(route('settings-overtime.update'), overtimeSettingsPayload([
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined->value,
        'overtime_weekly_anomaly_threshold_hours' => 14.5,
        'overtime_retroactive_request_days' => 30,
    ]))->assertRedirect();

    $setting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();

    expect($setting->overtime_authorization_mode)->toBe(OvertimeAuthorizationMode::Combined)
        ->and($setting->overtime_weekly_anomaly_threshold_hours)->toBe(14.5)
        ->and($setting->overtime_retroactive_request_days)->toBe(30)
        ->and($settings->get('overtime_weekly_anomaly_threshold_hours'))->toEqual(14.5);
});

test('an unknown authorization mode or out-of-range value is rejected', function () {
    $admin = overtimeSettingsAdmin();

    $this->actingAs($admin)
        ->patch(route('settings-overtime.update'), overtimeSettingsPayload([
            'overtime_authorization_mode' => 'whenever',
            'overtime_weekly_anomaly_threshold_hours' => -1,
            'overtime_retroactive_request_days' => 'soon',
        ]))
        ->assertSessionHasErrors([
            'overtime_authorization_mode',
            'overtime_weekly_anomaly_threshold_hours',
            'overtime_retroactive_request_days',
        ]);
});

test('saving fires the observer, clearing the cache so reads are never stale', function () {
    $admin = overtimeSettingsAdmin();
    $this->actingAs($admin);
    $cacheKey = 'org_settings:'.$admin->organization_id;
    $settings = app(OrganizationSettings::class);

    // Warm the scalar-read cache with the current (default) value.
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::Combined);
    expect(Cache::has($cacheKey))->toBeTrue();

    $this->patch(route('settings-overtime.update'), overtimeSettingsPayload([
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization->value,
    ]));

    // The observer's saved() hook invalidated the cache, so the next read
    // reflects the new value instead of the stale cached one.
    expect(Cache::has($cacheKey))->toBeFalse()
        ->and($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PreAuthorization);
});

test('the overtime policy is organization-scoped in both directions', function () {
    $adminA = overtimeSettingsAdmin();
    $orgB = Organization::factory()->create();
    $adminB = overtimeSettingsAdmin($orgB);
    $settingB = Setting::factory()->create([
        'organization_id' => $orgB->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
        'overtime_retroactive_request_days' => 21,
    ]);

    $settings = app(OrganizationSettings::class);

    $this->actingAs($adminA)->patch(route('settings-overtime.update'), overtimeSettingsPayload([
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined->value,
        'overtime_retroactive_request_days' => 5,
    ]))->assertRedirect();

    // B's policy survived A's write untouched.
    expect($settingB->refresh()->overtime_authorization_mode)->toBe(OvertimeAuthorizationMode::PreAuthorization)
        ->and($settingB->overtime_retroactive_request_days)->toBe(21);

    // Each tenant reads its own policy and never the other's — and B's row is
    // not even visible to a query made while A is the active tenant.
    $this->actingAs($adminA);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::Combined)
        ->and(Setting::query()->where('organization_id', $orgB->id)->exists())->toBeFalse();

    $this->actingAs($adminB);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PreAuthorization);
});

test('a write invalidates only the acting organization cache', function () {
    $adminA = overtimeSettingsAdmin();
    $orgB = Organization::factory()->create();
    $adminB = overtimeSettingsAdmin($orgB);
    Setting::factory()->create([
        'organization_id' => $orgB->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);

    $settings = app(OrganizationSettings::class);

    // Warm both tenants' caches.
    $this->actingAs($adminB);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PreAuthorization);
    $this->actingAs($adminA);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::Combined);

    $this->patch(route('settings-overtime.update'), overtimeSettingsPayload([
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined->value,
    ]))->assertRedirect();

    expect(Cache::has('org_settings:'.$adminA->organization_id))->toBeFalse()
        ->and(Cache::has('org_settings:'.$orgB->id))->toBeTrue();
});

test('every overtime field and option has a Spanish label', function () {
    app()->setLocale('es');

    $keys = collect([
        'overtime_authorization_mode',
        'overtime_weekly_anomaly_threshold_hours',
        'overtime_retroactive_request_days',
        'overtime_counts_pre_shift_excess',
    ])->flatMap(fn (string $field): array => [
        "ui.settings.overtime.fields.{$field}.label",
        "ui.settings.overtime.fields.{$field}.hint",
    ]);

    foreach ($keys as $key) {
        // A missing key makes Laravel echo the key itself back.
        expect(__($key, locale: 'es'))->not->toBe($key);
    }

    // The enum labels the select renders come from the same catalogue.
    expect(collect(OvertimeAuthorizationMode::options())->pluck('label')->all())
        ->toBe(['Autorización previa', 'Revisión posterior', 'Combinado']);
});

// --- Pre-shift excess policy (KOL-38) ---

test('an admin can turn on counting early arrival and the calculation engine reads it back', function () {
    $admin = overtimeSettingsAdmin();
    $this->actingAs($admin);
    $settings = app(OrganizationSettings::class);

    // Warm the cache with the default so the update has something to invalidate.
    expect($settings->overtimeCountsPreShiftExcess())->toBeFalse();

    $this->patch(route('settings-overtime.update'), overtimeSettingsPayload([
        'overtime_counts_pre_shift_excess' => true,
    ]))->assertRedirect();

    expect($settings->overtimeCountsPreShiftExcess())->toBeTrue()
        ->and(Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail()
            ->overtime_counts_pre_shift_excess)->toBeTrue();
});

test('one organization enabling early arrival leaves the others on the default', function () {
    $adminA = overtimeSettingsAdmin();
    $orgB = Organization::factory()->create();

    $this->actingAs($adminA)->patch(route('settings-overtime.update'), overtimeSettingsPayload([
        'overtime_counts_pre_shift_excess' => true,
    ]))->assertRedirect();

    $settings = app(OrganizationSettings::class);

    expect($settings->overtimeCountsPreShiftExcess($adminA->organization_id))->toBeTrue()
        ->and($settings->overtimeCountsPreShiftExcess($orgB->id))->toBeFalse();
});

test('a non-boolean pre-shift excess policy is rejected', function () {
    $admin = overtimeSettingsAdmin();

    $this->actingAs($admin)
        ->patch(route('settings-overtime.update'), overtimeSettingsPayload([
            'overtime_counts_pre_shift_excess' => 'sometimes',
        ]))
        ->assertSessionHasErrors('overtime_counts_pre_shift_excess');
});

// --- No tenant-wide compensation default (KOL-56) ---

test('the organization carries no default overtime compensation type', function () {
    // Resolución 38 art. 43 requires the system to *offer* both compensation
    // modes, then fixes the fallback as law: absent a written pacto the hours
    // are paid. That is not an employer preference, and the pacto is per worker
    // (art. 45.3, art. 41 i), so there is no organization-level answer to store.
    // KOL-47 puts the choice on the agreement, where it belongs.
    $admin = overtimeSettingsAdmin();

    expect(Schema::hasColumn('settings', 'overtime_default_compensation_type'))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('settings-overtime.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('settings.overtime_default_compensation_type')
            ->missing('overtimeCompensationTypeOptions')
        );
});

// --- No tenant switch making a pacto mandatory (KOL-57) ---

test('the organization carries no pacto requirement switch', function () {
    // Art. 32 requires overtime to be agreed in writing, but the absence of the
    // agreement does not stop the hours being overtime: the DT reality criterion
    // makes hours worked with the employer's knowledge payable regardless. A
    // switch that made such a record unapprovable would produce an unlawful
    // outcome, so a missing pacto is a flag demanding a written justification
    // (KOL-42), never a bar. See decision-1.
    $admin = overtimeSettingsAdmin();

    expect(Schema::hasColumn('settings', 'overtime_requires_pact'))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('settings-overtime.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('settings.overtime_requires_pact')
        );
});
