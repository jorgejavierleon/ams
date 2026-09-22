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
function documentSettingsAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * A complete, valid document settings payload. The update endpoint saves the
 * form as a whole, so every test that patches has to send every key.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function documentSettingsPayload(array $overrides = []): array
{
    return [
        'documents_signature_enabled' => false,
        'documents_require_ordered_signing' => false,
        ...$overrides,
    ];
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('settings-documents.edit'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('settings-documents.edit'))
        ->assertForbidden();

    $this->actingAs($employee)
        ->patch(route('settings-documents.update'), [])
        ->assertForbidden();
});

// --- Edit ---

test('admin can view the documents page, creating the row with defaults', function () {
    $admin = documentSettingsAdmin();

    $this->actingAs($admin)
        ->get(route('settings-documents.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/documents')
            ->where('settings.documents_signature_enabled', false)
            ->where('settings.documents_require_ordered_signing', false)
        );

    $this->assertDatabaseHas('settings', [
        'organization_id' => $admin->organization_id,
        'documents_signature_enabled' => false,
    ]);
});

// --- Update ---

test('admin can update the document settings atomically and they persist', function () {
    $admin = documentSettingsAdmin();

    $payload = documentSettingsPayload([
        'documents_signature_enabled' => true,
        'documents_require_ordered_signing' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('settings-documents.update'), $payload)
        ->assertRedirect();

    $setting = Setting::query()->where('organization_id', $admin->organization_id)->firstOrFail();

    expect($setting->documents_signature_enabled)->toBeTrue()
        ->and($setting->documents_require_ordered_signing)->toBeTrue();
});

test('saving fires the observer, clearing the cache so reads are never stale', function () {
    $admin = documentSettingsAdmin();
    $this->actingAs($admin);
    $cacheKey = 'org_settings:'.$admin->organization_id;
    $settings = app(OrganizationSettings::class);

    // Warm the scalar-read cache with the current (default) value.
    expect($settings->get('documents_signature_enabled'))->toBeFalse();
    expect(Cache::has($cacheKey))->toBeTrue();

    $this->patch(route('settings-documents.update'), documentSettingsPayload([
        'documents_signature_enabled' => true,
    ]));

    // The observer's saved() hook invalidated the cache, so the next read
    // reflects the new value instead of the stale cached one.
    expect(Cache::has($cacheKey))->toBeFalse()
        ->and($settings->get('documents_signature_enabled'))->toBeTrue();
});

test('updating is scoped to the current organization', function () {
    $admin = documentSettingsAdmin();
    $otherOrg = Organization::factory()->create();
    $otherSetting = Setting::factory()->create([
        'organization_id' => $otherOrg->id,
        'documents_signature_enabled' => false,
    ]);

    $this->actingAs($admin)->patch(route('settings-documents.update'), documentSettingsPayload([
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
    $admin = documentSettingsAdmin();

    $this->actingAs($admin)
        ->patch(route('settings-documents.update'), documentSettingsPayload([
            'documents_signature_enabled' => 'maybe',
        ]))
        ->assertSessionHasErrors('documents_signature_enabled');
});

test('every document field has a Spanish label', function () {
    app()->setLocale('es');

    $keys = collect([
        'documents_signature_enabled',
        'documents_require_ordered_signing',
    ])->flatMap(fn (string $field): array => [
        "ui.settings.documents.fields.{$field}.label",
        "ui.settings.documents.fields.{$field}.hint",
    ]);

    foreach ($keys as $key) {
        // A missing key makes Laravel echo the key itself back.
        expect(__($key, locale: 'es'))->not->toBe($key);
    }
});
