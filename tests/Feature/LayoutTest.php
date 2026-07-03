<?php

use App\Models\User;

test('dashboard renders the correct Inertia component for authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('unauthenticated requests to authenticated routes redirect to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('shared Inertia props include auth user, flash data, and permissions', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->has('auth.permissions')
            ->has('flash.success')
            ->has('flash.error')
            ->has('flash.warning')
        );
});

test('flash success message is present in Inertia shared data after redirect', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Record saved.'])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('flash.success', 'Record saved.')
        );
});
