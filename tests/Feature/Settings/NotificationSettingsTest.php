<?php

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrganizationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
function notificationSettingsAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * A complete, valid notification settings payload. The update endpoint saves
 * the form as a whole, so every test that patches has to send every key.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationSettingsPayload(array $overrides = []): array
{
    return [
        'employee_missing_in_notification' => true,
        'employee_missing_out_notification' => true,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => true,
        'leave_approval_notification' => true,
        ...$overrides,
    ];
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('settings-notifications.edit'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('settings-notifications.edit'))
        ->assertForbidden();

    $this->actingAs($employee)
        ->patch(route('settings-notifications.update'), [])
        ->assertForbidden();
});

// --- Edit ---

test('admin can view the notifications page, creating the row with defaults', function () {
    $admin = notificationSettingsAdmin();

    $this->actingAs($admin)
        ->get(route('settings-notifications.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/notifications')
            ->where('settings.employee_missing_in_notification', true)
            ->where('settings.leave_approval_notification', true)
        );

    $this->assertDatabaseHas('settings', [
        'organization_id' => $admin->organization_id,
        'employee_missing_in_notification' => true,
        'leave_approval_notification' => true,
    ]);
});

// --- Update ---

test('admin can update the notification settings atomically and they persist', function () {
    $admin = notificationSettingsAdmin();

    $payload = notificationSettingsPayload([
        'employee_missing_in_notification' => false,
        'employee_missing_out_notification' => false,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => false,
        'leave_approval_notification' => false,
    ]);

    $this->actingAs($admin)
        ->patch(route('settings-notifications.update'), $payload)
        ->assertRedirect();

    $setting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();

    expect($setting->employee_missing_in_notification)->toBeFalse()
        ->and($setting->employee_missing_out_notification)->toBeFalse()
        ->and($setting->employer_missing_in_notification)->toBeTrue()
        ->and($setting->employer_missing_out_notification)->toBeFalse()
        ->and($setting->leave_approval_notification)->toBeFalse();
});

test('saving fires the observer, clearing the cache so reads are never stale', function () {
    $admin = notificationSettingsAdmin();
    $this->actingAs($admin);
    $cacheKey = 'org_settings:'.$admin->organization_id;
    $settings = app(OrganizationSettings::class);

    // Warm the scalar-read cache with the current (default) value.
    expect($settings->get('leave_approval_notification'))->toBeTrue();
    expect(Cache::has($cacheKey))->toBeTrue();

    $this->patch(route('settings-notifications.update'), notificationSettingsPayload([
        'leave_approval_notification' => false,
    ]));

    // The observer's saved() hook invalidated the cache, so the next read
    // reflects the new value instead of the stale cached one.
    expect(Cache::has($cacheKey))->toBeFalse()
        ->and($settings->get('leave_approval_notification'))->toBeFalse();
});

test('updating is scoped to the current organization', function () {
    $admin = notificationSettingsAdmin();
    $otherOrg = Organization::factory()->create();
    $otherSetting = Setting::factory()->create([
        'organization_id' => $otherOrg->id,
        'leave_approval_notification' => true,
    ]);

    $this->actingAs($admin)->patch(route('settings-notifications.update'), notificationSettingsPayload([
        'leave_approval_notification' => false,
    ]));

    // The other organization's settings are untouched, and the admin's own row
    // was created/updated for their organization only.
    expect($otherSetting->refresh()->leave_approval_notification)->toBeTrue();

    $adminSetting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();
    expect($adminSetting->leave_approval_notification)->toBeFalse();
});

test('a non-boolean setting value is rejected', function () {
    $admin = notificationSettingsAdmin();

    $this->actingAs($admin)
        ->patch(route('settings-notifications.update'), notificationSettingsPayload([
            'employee_missing_in_notification' => 'maybe',
        ]))
        ->assertSessionHasErrors('employee_missing_in_notification');
});

test('every notification field has a Spanish label', function () {
    app()->setLocale('es');

    $keys = collect([
        'employee_missing_in_notification',
        'employee_missing_out_notification',
        'employer_missing_in_notification',
        'employer_missing_out_notification',
        'leave_approval_notification',
    ])->flatMap(fn (string $field): array => [
        "ui.settings.notifications.fields.{$field}.label",
        "ui.settings.notifications.fields.{$field}.hint",
    ]);

    foreach ($keys as $key) {
        // A missing key makes Laravel echo the key itself back.
        expect(__($key, locale: 'es'))->not->toBe($key);
    }
});
