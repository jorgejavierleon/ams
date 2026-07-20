<?php

use App\Models\Organization;
use App\Models\User;

test('a dt login visit with coexisting sessions redirects to the dt dashboard', function () {
    $organization = Organization::factory()->create();
    $inspector = User::factory()->dtUser()->create();
    $superAdmin = User::factory()->saasUser()->create();

    // Both guards hold a login in the same session, as happens when a user
    // signs into both panels without logging out of the other.
    $this->actingAs($inspector, 'dt')
        ->actingAs($superAdmin, 'saas')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.login'))
        ->assertRedirect(route('dt.dashboard'));
});

test('a saas login visit with coexisting sessions redirects to the saas dashboard', function () {
    $inspector = User::factory()->dtUser()->create();
    $superAdmin = User::factory()->saasUser()->create();

    $this->actingAs($inspector, 'dt')
        ->actingAs($superAdmin, 'saas')
        ->get(route('saas.login'))
        ->assertRedirect(route('saas.dashboard'));
});

test('a saas-only user can still reach the dt login page', function () {
    $superAdmin = User::factory()->saasUser()->create();

    $this->actingAs($superAdmin, 'saas')
        ->get(route('dt.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/dt-login'));
});
