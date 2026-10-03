<?php

use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;

uses()->group('saas');

function saasEmailLimitsAdmin(): User
{
    return User::factory()->saasUser()->create();
}

// --- Default calculation ---

test('an unoverridden organization defaults to active users times the baseline', function () {
    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 10]);
    $organization = Organization::factory()->create();
    User::factory()->count(8)->create(['organization_id' => $organization->id, 'is_active' => true]);
    User::factory()->count(2)->create(['organization_id' => $organization->id, 'is_active' => false]);

    expect($organization->defaultEmailLimit())->toBe(80)
        ->and($organization->softEmailLimit())->toBe(80)
        ->and($organization->hardEmailLimit())->toBe(80);
});

test('the default limit is 0 while the baseline is unset', function () {
    $organization = Organization::factory()->create();
    User::factory()->count(5)->create(['organization_id' => $organization->id, 'is_active' => true]);

    expect($organization->defaultEmailLimit())->toBe(0);
});

// --- Independent overrides ---

test('overriding the soft limit leaves the hard limit on its default', function () {
    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 10]);
    $organization = Organization::factory()->create(['soft_email_limit_override' => 50]);
    User::factory()->count(8)->create(['organization_id' => $organization->id, 'is_active' => true]);

    expect($organization->softEmailLimit())->toBe(50)
        ->and($organization->hardEmailLimit())->toBe(80);
});

// --- Sticky overrides across baseline changes ---

test('changing the baseline recalculates only organizations without an override on that figure', function () {
    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 10]);

    $acme = Organization::factory()->create(['hard_email_limit_override' => 120]);
    User::factory()->count(8)->create(['organization_id' => $acme->id, 'is_active' => true]);

    $beta = Organization::factory()->create();
    User::factory()->count(8)->create(['organization_id' => $beta->id, 'is_active' => true]);

    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 15]);

    expect($acme->hardEmailLimit())->toBe(120)
        ->and($beta->hardEmailLimit())->toBe(120);
});

// --- Organization edit page ---

test('the edit page exposes the effective email limits and the platform baseline', function () {
    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 10]);
    $organization = Organization::factory()->create(['soft_email_limit_override' => 50]);
    User::factory()->count(8)->create(['organization_id' => $organization->id, 'is_active' => true]);

    $this->actingAs(saasEmailLimitsAdmin(), 'saas')
        ->get(route('saas.organizations.edit', $organization))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('emailLimits.baseline', 10)
                ->where('emailLimits.activeUsersCount', 8)
                ->where('emailLimits.defaultLimit', 80)
                ->where('emailLimits.softLimit', 50)
                ->where('emailLimits.hardLimit', 80)
                ->where('emailLimits.softOverride', 50)
                ->where('emailLimits.hardOverride', null)
        );
});

// --- Updating overrides ---

test('a saas admin can override an organization soft and hard limit independently', function () {
    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 10]);
    $acme = Organization::factory()->create();
    User::factory()->count(8)->create(['organization_id' => $acme->id, 'is_active' => true]);
    $beta = Organization::factory()->create();
    User::factory()->count(3)->create(['organization_id' => $beta->id, 'is_active' => true]);

    $this->actingAs(saasEmailLimitsAdmin(), 'saas')
        ->patch(route('saas.organizations.email-limits.update', $acme), [
            'soft_limit_override' => 50,
            'hard_limit_override' => null,
        ])
        ->assertRedirect(route('saas.organizations.edit', $acme));

    expect($acme->fresh())
        ->soft_email_limit_override->toBe(50)
        ->hard_email_limit_override->toBeNull()
        ->and($beta->fresh()->softEmailLimit())->toBe(30);
});

test('clearing an override returns the organization to the computed default', function () {
    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 10]);
    $organization = Organization::factory()->create(['soft_email_limit_override' => 999]);
    User::factory()->count(8)->create(['organization_id' => $organization->id, 'is_active' => true]);

    $this->actingAs(saasEmailLimitsAdmin(), 'saas')
        ->patch(route('saas.organizations.email-limits.update', $organization), [
            'soft_limit_override' => null,
            'hard_limit_override' => null,
        ])
        ->assertRedirect(route('saas.organizations.edit', $organization));

    expect($organization->fresh()->softEmailLimit())->toBe(80);
});

test('overriding an organization limit rejects non-positive values', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(saasEmailLimitsAdmin(), 'saas')
        ->patch(route('saas.organizations.email-limits.update', $organization), [
            'soft_limit_override' => 0,
            'hard_limit_override' => -5,
        ])
        ->assertSessionHasErrors(['soft_limit_override', 'hard_limit_override']);
});

test('non-saas users cannot override an organization email limit', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user, 'saas')
        ->patch(route('saas.organizations.email-limits.update', $organization), [
            'soft_limit_override' => 50,
        ])
        ->assertForbidden();
});
