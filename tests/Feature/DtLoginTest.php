<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses()->group('dt');

// --- Login page ---

test('dt login page renders', function () {
    $this->get('/dt/login')->assertOk();
});

test('dt login redirects authenticated dt users away', function () {
    $user = User::factory()->dtUser()->create();

    $this->actingAs($user, 'dt')
        ->get('/dt/login')
        ->assertRedirect();
});

// --- Login attempt ---

test('dt user can log in with valid credentials', function () {
    $user = User::factory()->dtUser()->create([
        'email' => 'inspector@dt.gov.cl',
        'password' => Hash::make('secret123'),
    ]);

    $this->post('/dt/login', [
        'email' => 'inspector@dt.gov.cl',
        'password' => 'secret123',
    ])->assertRedirect('/dt/dashboard');

    $this->assertAuthenticatedAs($user, 'dt');
});

test('non-dt user cannot log in via dt login', function () {
    User::factory()->create([
        'email' => 'admin@company.com',
        'password' => Hash::make('secret123'),
        'is_dt' => false,
    ]);

    $this->post('/dt/login', [
        'email' => 'admin@company.com',
        'password' => 'secret123',
    ])->assertInvalid(['email']);

    $this->assertGuest('dt');
});

test('dt login fails with wrong password', function () {
    User::factory()->dtUser()->create([
        'email' => 'inspector@dt.gov.cl',
        'password' => Hash::make('correct'),
    ]);

    $this->post('/dt/login', [
        'email' => 'inspector@dt.gov.cl',
        'password' => 'wrong',
    ])->assertInvalid(['email']);

    $this->assertGuest('dt');
});

// --- Expired password redirect ---

test('dt user with expired password is redirected to password change', function () {
    $user = User::factory()->dtUser()->create([
        'password_changed_at' => now()->subDays(10),
    ]);

    $this->actingAs($user, 'dt')
        ->get('/dt/dashboard')
        ->assertRedirect('/dt/password/change');
});

test('dt user with active password can access dashboard', function () {
    $user = User::factory()->dtUser()->create([
        'password_changed_at' => now()->subDay(),
    ]);
    $organization = Organization::factory()->create();

    $this->actingAs($user, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get('/dt/dashboard')
        ->assertOk();
});

test('dt user with expired password can still access password change page', function () {
    $user = User::factory()->dtUser()->create([
        'password_changed_at' => now()->subDays(10),
    ]);

    $this->actingAs($user, 'dt')
        ->get('/dt/password/change')
        ->assertOk();
});

test('dt user can change their expired password', function () {
    $user = User::factory()->dtUser()->create([
        'password_changed_at' => now()->subDays(10),
    ]);

    $this->actingAs($user, 'dt')
        ->post('/dt/password/change', [
            'password' => 'NewSecure123!',
            'password_confirmation' => 'NewSecure123!',
        ])
        ->assertRedirect('/dt/dashboard');

    expect($user->fresh()->password_changed_at->diffInDays(now()))->toBeLessThanOrEqual(1);
});

// --- Guard separation ---

test('authenticated web user cannot access dt dashboard', function () {
    $user = User::factory()->create(['is_dt' => false]);

    $this->actingAs($user, 'web')
        ->get('/dt/dashboard')
        ->assertRedirect('/dt/login');
});

test('authenticated dt user is not authenticated in web guard', function () {
    $user = User::factory()->dtUser()->create();

    $this->actingAs($user, 'dt');

    $this->assertGuest('web');
});

// --- Forgot password ---

test('dt forgot password page renders', function () {
    $this->get('/dt/forgot-password')->assertOk();
});

test('dt forgot password rejects non-dt-gov-cl emails', function () {
    $this->post('/dt/forgot-password', [
        'email' => 'someone@gmail.com',
    ])->assertInvalid(['email']);
});

test('dt forgot password accepts dt.gov.cl email and creates user', function () {
    $this->post('/dt/forgot-password', [
        'email' => 'new@dt.gov.cl',
    ])->assertSessionHas('status');

    expect(User::where('email', 'new@dt.gov.cl')->where('is_dt', true)->exists())->toBeTrue();
});

// --- Logout ---

test('dt user can log out', function () {
    $user = User::factory()->dtUser()->create();

    $this->actingAs($user, 'dt')
        ->post('/dt/logout')
        ->assertRedirect('/dt/login');

    $this->assertGuest('dt');
});

test('authenticated dt user visiting dt login is redirected to dt dashboard', function () {
    $user = User::factory()->dtUser()->create();

    $this->actingAs($user, 'dt')
        ->get('/dt/login')
        ->assertRedirect('/dt/dashboard');
});
