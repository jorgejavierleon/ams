<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** The factory's password is 'password'; the wrong one below never matches it. */
function rateLimitApiEmployee(): User
{
    return User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);
}

function attemptToken(User $employee, string $password = 'wrong-password'): TestResponse
{
    return test()->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => $password,
        'device_name' => 'pixel-8',
    ]);
}

// --- Token issuance ---

test('the sixth failed sign-in for one email and IP is blocked', function () {
    $employee = rateLimitApiEmployee();

    foreach (range(1, 5) as $attempt) {
        attemptToken($employee)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    attemptToken($employee)->assertStatus(429);
});

test('attempts under the limit behave exactly as before', function () {
    $employee = rateLimitApiEmployee();

    foreach (range(1, 4) as $attempt) {
        attemptToken($employee)->assertStatus(422);
    }

    attemptToken($employee, 'password')
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('signing in correctly clears the failure counter', function () {
    $employee = rateLimitApiEmployee();

    foreach (range(1, 4) as $attempt) {
        attemptToken($employee)->assertStatus(422);
    }

    attemptToken($employee, 'password')->assertOk();

    // Without the clear, the successful attempt would have left the key at five
    // and the very next mistype would be a 429.
    foreach (range(1, 5) as $attempt) {
        attemptToken($employee)->assertStatus(422);
    }

    attemptToken($employee)->assertStatus(429);
});

test('one employee exhausting their attempts does not block a colleague on the same premise IP', function () {
    $employee = rateLimitApiEmployee();
    $colleague = rateLimitApiEmployee();

    foreach (range(1, 5) as $attempt) {
        attemptToken($employee)->assertStatus(422);
    }

    attemptToken($employee)->assertStatus(429);

    // Same IP, different email: the colleague starts their shift normally.
    attemptToken($colleague)->assertStatus(422);
    attemptToken($colleague, 'password')->assertOk();
});

test('a throttled sign-in tells the app when to retry', function () {
    $employee = rateLimitApiEmployee();

    foreach (range(1, 5) as $attempt) {
        attemptToken($employee)->assertStatus(422);
    }

    $response = attemptToken($employee)->assertStatus(429);

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and($response->headers->get('X-RateLimit-Limit'))->toBe('5')
        ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('0')
        ->and($response->headers->has('X-RateLimit-Reset'))->toBeTrue();
});

// --- Password change ---

test('a stolen device cannot retry current_password indefinitely', function () {
    Sanctum::actingAs(rateLimitApiEmployee());

    foreach (range(1, 6) as $attempt) {
        $this->putJson('/api/v1/user/password', [
            'current_password' => 'guess-'.$attempt,
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])->assertStatus(422);
    }

    $this->putJson('/api/v1/user/password', [
        'current_password' => 'guess-7',
        'password' => 'Marcaje-2026!',
        'password_confirmation' => 'Marcaje-2026!',
    ])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('the password limit is keyed per employee, not per premise IP', function () {
    $employee = rateLimitApiEmployee();
    $colleague = rateLimitApiEmployee();

    Sanctum::actingAs($employee);

    foreach (range(1, 6) as $attempt) {
        $this->putJson('/api/v1/user/password', [
            'current_password' => 'guess-'.$attempt,
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])->assertStatus(422);
    }

    Sanctum::actingAs($colleague);

    $this->putJson('/api/v1/user/password', [
        'current_password' => 'guess-1',
        'password' => 'Marcaje-2026!',
        'password_confirmation' => 'Marcaje-2026!',
    ])->assertStatus(422);
});

// --- Baseline ---

test('the baseline limit covers the rest of the mobile surface', function () {
    $employee = rateLimitApiEmployee();
    $colleague = rateLimitApiEmployee();

    Sanctum::actingAs($employee);

    foreach (range(1, 60) as $request) {
        $this->getJson('/api/v1/user')->assertOk();
    }

    $this->getJson('/api/v1/user')
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    // Authenticated traffic is keyed per employee, so a phone that hammers the
    // API does not take the rest of the premise down with it.
    Sanctum::actingAs($colleague);

    $this->getJson('/api/v1/user')->assertOk();
});

test('no route in the mobile API is unlimited', function () {
    $router = app('router');

    $mobileRoutes = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'));

    expect($mobileRoutes)->not->toBeEmpty();

    $mobileRoutes->each(function ($route) use ($router) {
        $throttles = collect($router->gatherRouteMiddleware($route))
            ->filter(fn ($middleware) => is_string($middleware)
                && str_contains(strtolower($middleware), 'throttle'));

        expect($throttles)->not->toBeEmpty("Route {$route->uri()} carries no throttle middleware.");
    });
});
