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
function settingsAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
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

    $payload = [
        'employee_missing_in_notification' => false,
        'employee_missing_out_notification' => false,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => false,
        'leave_approval_notification' => false,
        'documents_signature_enabled' => true,
        'documents_require_ordered_signing' => true,
    ];

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

    $this->patch(route('organization-settings.update'), [
        'employee_missing_in_notification' => true,
        'employee_missing_out_notification' => true,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => true,
        'leave_approval_notification' => true,
        'documents_signature_enabled' => true,
        'documents_require_ordered_signing' => false,
    ]);

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

    $this->actingAs($admin)->patch(route('organization-settings.update'), [
        'employee_missing_in_notification' => true,
        'employee_missing_out_notification' => true,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => true,
        'leave_approval_notification' => true,
        'documents_signature_enabled' => true,
        'documents_require_ordered_signing' => true,
    ]);

    // The other organization's settings are untouched, and the admin's own row
    // was created/updated for their organization only.
    expect($otherSetting->refresh()->documents_signature_enabled)->toBeFalse();

    $adminSetting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();
    expect($adminSetting->documents_signature_enabled)->toBeTrue();
});

test('a non-boolean setting value is rejected', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->patch(route('organization-settings.update'), [
            'employee_missing_in_notification' => 'maybe',
        ])
        ->assertSessionHasErrors('employee_missing_in_notification');
});
