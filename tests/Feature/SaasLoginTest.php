<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses()->group('saas');

// --- Login page ---

test('saas login page renders', function () {
    $this->get('/saas/login')->assertOk();
});

test('saas login redirects authenticated saas users away', function () {
    $user = User::factory()->saasUser()->create();

    $this->actingAs($user, 'saas')
        ->get('/saas/login')
        ->assertRedirect();
});

// --- Login attempt ---

test('saas user can log in with valid credentials', function () {
    $user = User::factory()->saasUser()->create([
        'email' => 'admin@ams.cl',
        'password' => Hash::make('secret123'),
    ]);

    $this->post('/saas/login', [
        'email' => 'admin@ams.cl',
        'password' => 'secret123',
    ])->assertRedirect('/saas/dashboard');

    $this->assertAuthenticatedAs($user, 'saas');
});

test('non-saas user cannot log in via saas login', function () {
    User::factory()->create([
        'email' => 'employee@company.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->post('/saas/login', [
        'email' => 'employee@company.com',
        'password' => 'secret123',
    ])->assertInvalid(['email']);

    $this->assertGuest('saas');
});

test('saas login fails with wrong password', function () {
    User::factory()->saasUser()->create([
        'email' => 'admin@ams.cl',
        'password' => Hash::make('correct'),
    ]);

    $this->post('/saas/login', [
        'email' => 'admin@ams.cl',
        'password' => 'wrong',
    ])->assertInvalid(['email']);

    $this->assertGuest('saas');
});

// --- Guard isolation ---

test('unauthenticated access to saas dashboard redirects to saas login', function () {
    $this->get('/saas/dashboard')->assertRedirect('/saas/login');
});

test('authenticated web user cannot access saas dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->get('/saas/dashboard')
        ->assertRedirect('/saas/login');
});

test('authenticated dt user cannot access saas dashboard', function () {
    $user = User::factory()->dtUser()->create();

    $this->actingAs($user, 'dt')
        ->get('/saas/dashboard')
        ->assertRedirect('/saas/login');
});

test('authenticated saas user is not authenticated in web guard', function () {
    $user = User::factory()->saasUser()->create();

    $this->actingAs($user, 'saas');

    $this->assertGuest('web');
});

test('authenticated saas user is not authenticated in dt guard', function () {
    $user = User::factory()->saasUser()->create();

    $this->actingAs($user, 'saas');

    $this->assertGuest('dt');
});

// --- Logout ---

test('saas user can log out', function () {
    $user = User::factory()->saasUser()->create();

    $this->actingAs($user, 'saas')
        ->post('/saas/logout')
        ->assertRedirect('/saas/login');

    $this->assertGuest('saas');
});

test('authenticated saas user visiting saas login is redirected to saas dashboard', function () {
    $user = User::factory()->saasUser()->create();

    $this->actingAs($user, 'saas')
        ->get('/saas/login')
        ->assertRedirect('/saas/dashboard');
});
