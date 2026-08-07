<?php

use App\Enums\OvertimeAuthorizationMode;
use App\Enums\OvertimeCompensationType;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrganizationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
function settingsAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * A complete, valid settings payload. The update endpoint saves the form as a
 * whole, so every test that patches has to send every key.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return [
        'employee_missing_in_notification' => true,
        'employee_missing_out_notification' => true,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => true,
        'leave_approval_notification' => true,
        'documents_signature_enabled' => false,
        'documents_require_ordered_signing' => false,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PostHoc->value,
        'overtime_requires_pact' => false,
        'overtime_weekly_anomaly_threshold_hours' => 10,
        'overtime_retroactive_request_days' => 7,
        'overtime_default_compensation_type' => OvertimeCompensationType::Payment->value,
        ...$overrides,
    ];
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('organization-settings.edit'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('organization-settings.edit'))
        ->assertForbidden();

    $this->actingAs($employee)
        ->patch(route('organization-settings.update'), [])
        ->assertForbidden();
});

// --- Index ---

test('admin can view the settings page, creating the row with defaults', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->get(route('organization-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization-settings')
            ->where('settings.employee_missing_in_notification', true)
            ->where('settings.documents_signature_enabled', false)
            ->where('settings.documents_require_ordered_signing', false)
        );

    $this->assertDatabaseHas('settings', [
        'organization_id' => $admin->organization_id,
        'employee_missing_in_notification' => true,
        'documents_signature_enabled' => false,
    ]);
});

// --- Update ---

test('admin can update all settings atomically and they persist', function () {
    $admin = settingsAdmin();

    $payload = settingsPayload([
        'employee_missing_in_notification' => false,
        'employee_missing_out_notification' => false,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => false,
        'leave_approval_notification' => false,
        'documents_signature_enabled' => true,
        'documents_require_ordered_signing' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('organization-settings.update'), $payload)
        ->assertRedirect();

    $setting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();

    expect($setting->employee_missing_in_notification)->toBeFalse()
        ->and($setting->employee_missing_out_notification)->toBeFalse()
        ->and($setting->employer_missing_in_notification)->toBeTrue()
        ->and($setting->employer_missing_out_notification)->toBeFalse()
        ->and($setting->leave_approval_notification)->toBeFalse()
        ->and($setting->documents_signature_enabled)->toBeTrue()
        ->and($setting->documents_require_ordered_signing)->toBeTrue();
});

test('saving fires the observer, clearing the cache so reads are never stale', function () {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $cacheKey = 'org_settings:'.$admin->organization_id;
    $settings = app(OrganizationSettings::class);

    // Warm the scalar-read cache with the current (default) value.
    expect($settings->get('documents_signature_enabled'))->toBeFalse();
    expect(Cache::has($cacheKey))->toBeTrue();

    $this->patch(route('organization-settings.update'), settingsPayload([
        'documents_signature_enabled' => true,
    ]));

    // The observer's saved() hook invalidated the cache, so the next read
    // reflects the new value instead of the stale cached one.
    expect(Cache::has($cacheKey))->toBeFalse()
        ->and($settings->get('documents_signature_enabled'))->toBeTrue();
});

test('updating is scoped to the current organization', function () {
    $admin = settingsAdmin();
    $otherOrg = Organization::factory()->create();
    $otherSetting = Setting::factory()->create([
        'organization_id' => $otherOrg->id,
        'documents_signature_enabled' => false,
    ]);

    $this->actingAs($admin)->patch(route('organization-settings.update'), settingsPayload([
        'documents_signature_enabled' => true,
        'documents_require_ordered_signing' => true,
    ]));

    // The other organization's settings are untouched, and the admin's own row
    // was created/updated for their organization only.
    expect($otherSetting->refresh()->documents_signature_enabled)->toBeFalse();

    $adminSetting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();
    expect($adminSetting->documents_signature_enabled)->toBeTrue();
});

test('a non-boolean setting value is rejected', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->patch(route('organization-settings.update'), settingsPayload([
            'employee_missing_in_notification' => 'maybe',
        ]))
        ->assertSessionHasErrors('employee_missing_in_notification');
});

// --- Overtime policy (KOL-37) ---

test('a brand-new organization gets the legal overtime defaults', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->get(route('organization-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.overtime_authorization_mode', OvertimeAuthorizationMode::PostHoc->value)
            ->where('settings.overtime_requires_pact', false)
            // JSON has no float/int distinction, so compare numerically.
            ->where('settings.overtime_weekly_anomaly_threshold_hours', fn ($hours) => (float) $hours === 10.0)
            ->where('settings.overtime_retroactive_request_days', 7)
            // Resolución 38 art. 43: absent a written agreement, overtime is paid.
            ->where('settings.overtime_default_compensation_type', OvertimeCompensationType::Payment->value)
        );

    $setting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();

    expect($setting->overtime_authorization_mode)->toBe(OvertimeAuthorizationMode::PostHoc)
        ->and($setting->overtime_requires_pact)->toBeFalse()
        ->and($setting->overtime_weekly_anomaly_threshold_hours)->toBe(10.0)
        ->and($setting->overtime_retroactive_request_days)->toBe(7)
        ->and($setting->overtime_default_compensation_type)->toBe(OvertimeCompensationType::Payment);
});

test('the defaults are readable through the settings service without a query per read', function () {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $settings = app(OrganizationSettings::class);

    // First read creates the row and warms the cache; the rest never hit the database.
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PostHoc);

    DB::enableQueryLog();

    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PostHoc)
        ->and($settings->overtimeDefaultCompensationType())->toBe(OvertimeCompensationType::Payment)
        ->and($settings->get('overtime_weekly_anomaly_threshold_hours'))->toEqual(10.0)
        ->and($settings->get('overtime_retroactive_request_days'))->toEqual(7)
        ->and($settings->get('overtime_requires_pact'))->toBeFalse()
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

test('each authorization mode round-trips through the settings service', function (OvertimeAuthorizationMode $mode) {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $settings = app(OrganizationSettings::class);

    // Warm the cache with the default so the update has something to invalidate.
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PostHoc);

    $this->patch(route('organization-settings.update'), settingsPayload([
        'overtime_authorization_mode' => $mode->value,
    ]))->assertRedirect();

    expect($settings->overtimeAuthorizationMode())->toBe($mode);
})->with([
    OvertimeAuthorizationMode::PreAuthorization,
    OvertimeAuthorizationMode::PostHoc,
    OvertimeAuthorizationMode::Combined,
]);

test('the whole overtime policy persists and is read back typed', function () {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $settings = app(OrganizationSettings::class);

    $this->patch(route('organization-settings.update'), settingsPayload([
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined->value,
        'overtime_requires_pact' => true,
        'overtime_weekly_anomaly_threshold_hours' => 14.5,
        'overtime_retroactive_request_days' => 30,
        'overtime_default_compensation_type' => OvertimeCompensationType::RestDays->value,
    ]))->assertRedirect();

    $setting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();

    expect($setting->overtime_authorization_mode)->toBe(OvertimeAuthorizationMode::Combined)
        ->and($setting->overtime_requires_pact)->toBeTrue()
        ->and($setting->overtime_weekly_anomaly_threshold_hours)->toBe(14.5)
        ->and($setting->overtime_retroactive_request_days)->toBe(30)
        ->and($setting->overtime_default_compensation_type)->toBe(OvertimeCompensationType::RestDays)
        ->and($settings->overtimeDefaultCompensationType())->toBe(OvertimeCompensationType::RestDays)
        ->and($settings->get('overtime_weekly_anomaly_threshold_hours'))->toEqual(14.5);
});

test('an unknown authorization mode or compensation type is rejected', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->patch(route('organization-settings.update'), settingsPayload([
            'overtime_authorization_mode' => 'whenever',
            'overtime_default_compensation_type' => 'bitcoin',
            'overtime_weekly_anomaly_threshold_hours' => -1,
            'overtime_retroactive_request_days' => 'soon',
        ]))
        ->assertSessionHasErrors([
            'overtime_authorization_mode',
            'overtime_default_compensation_type',
            'overtime_weekly_anomaly_threshold_hours',
            'overtime_retroactive_request_days',
        ]);
});

test('every overtime field and option has a Spanish label', function () {
    app()->setLocale('es');

    $keys = [
        'ui.organization_settings.sections.overtime',
        ...collect([
            'overtime_authorization_mode',
            'overtime_requires_pact',
            'overtime_weekly_anomaly_threshold_hours',
            'overtime_retroactive_request_days',
            'overtime_default_compensation_type',
        ])->flatMap(fn (string $field): array => [
            "ui.organization_settings.fields.{$field}.label",
            "ui.organization_settings.fields.{$field}.hint",
        ]),
    ];

    foreach ($keys as $key) {
        // A missing key makes Laravel echo the key itself back.
        expect(__($key, locale: 'es'))->not->toBe($key);
    }

    // The enum labels the selects render come from the same catalogue.
    expect(collect(OvertimeAuthorizationMode::options())->pluck('label')->all())
        ->toBe(['Autorización previa', 'Revisión posterior', 'Combinado'])
        ->and(collect(OvertimeCompensationType::options())->pluck('label')->all())
        ->toBe(['Pago en remuneraciones', 'Días de descanso']);
});

test('the overtime policy is organization-scoped in both directions', function () {
    $adminA = settingsAdmin();
    $orgB = Organization::factory()->create();
    $adminB = settingsAdmin($orgB);
    $settingB = Setting::factory()->create([
        'organization_id' => $orgB->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
        'overtime_requires_pact' => true,
    ]);

    $settings = app(OrganizationSettings::class);

    $this->actingAs($adminA)->patch(route('organization-settings.update'), settingsPayload([
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined->value,
        'overtime_requires_pact' => false,
    ]))->assertRedirect();

    // B's policy survived A's write untouched.
    expect($settingB->refresh()->overtime_authorization_mode)->toBe(OvertimeAuthorizationMode::PreAuthorization)
        ->and($settingB->overtime_requires_pact)->toBeTrue();

    // Each tenant reads its own policy and never the other's — and B's row is
    // not even visible to a query made while A is the active tenant.
    $this->actingAs($adminA);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::Combined)
        ->and(Setting::query()->where('organization_id', $orgB->id)->exists())->toBeFalse();

    $this->actingAs($adminB);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PreAuthorization);
});

test('a write invalidates only the acting organization cache', function () {
    $adminA = settingsAdmin();
    $orgB = Organization::factory()->create();
    $adminB = settingsAdmin($orgB);
    Setting::factory()->create([
        'organization_id' => $orgB->id,
        'overtime_authorization_mode' => OvertimeAuthorizationMode::PreAuthorization,
    ]);

    $settings = app(OrganizationSettings::class);

    // Warm both tenants' caches.
    $this->actingAs($adminB);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PreAuthorization);
    $this->actingAs($adminA);
    expect($settings->overtimeAuthorizationMode())->toBe(OvertimeAuthorizationMode::PostHoc);

    $this->patch(route('organization-settings.update'), settingsPayload([
        'overtime_authorization_mode' => OvertimeAuthorizationMode::Combined->value,
    ]))->assertRedirect();

    expect(Cache::has('org_settings:'.$adminA->organization_id))->toBeFalse()
        ->and(Cache::has('org_settings:'.$orgB->id))->toBeTrue();
});
