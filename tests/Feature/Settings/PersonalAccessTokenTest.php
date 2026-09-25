<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The token routes require a recently-confirmed password (RequirePassword),
 * same as the security.edit page itself.
 */
function actingAsWithConfirmedPassword(User $user)
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

test("security page lists only the authenticated user's own active tokens", function () {
    $user = User::factory()->create();
    $user->createToken('My laptop');

    $other = User::factory()->create();
    $other->createToken("Someone else's token");

    actingAsWithConfirmedPassword($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'My laptop'),
        );
});

test('a user can create a personal access token', function () {
    $user = User::factory()->create();

    $response = actingAsWithConfirmedPassword($user)
        ->from(route('security.edit'))
        ->post(route('security.tokens.store'), ['name' => 'Claude agent']);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('newToken.name', 'Claude agent');

    expect($user->tokens()->where('name', 'Claude agent')->exists())->toBeTrue();
});

test('a token name is required', function () {
    $user = User::factory()->create();

    actingAsWithConfirmedPassword($user)
        ->from(route('security.edit'))
        ->post(route('security.tokens.store'), ['name' => ''])
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('security.edit'));
});

test('a token name must be unique among the user\'s own tokens', function () {
    $user = User::factory()->create();
    $user->createToken('Claude agent');

    actingAsWithConfirmedPassword($user)
        ->from(route('security.edit'))
        ->post(route('security.tokens.store'), ['name' => 'Claude agent'])
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('security.edit'));

    expect($user->tokens()->where('name', 'Claude agent')->count())->toBe(1);
});

test('creating a token requires a recently confirmed password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('security.tokens.store'), ['name' => 'Claude agent'])
        ->assertRedirect(route('password.confirm'));

    expect($user->tokens()->count())->toBe(0);
});

test('a user can revoke their own token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('My laptop');

    actingAsWithConfirmedPassword($user)
        ->from(route('security.edit'))
        ->delete(route('security.tokens.destroy', $token->accessToken->id))
        ->assertRedirect(route('security.edit'));

    expect($user->tokens()->count())->toBe(0);
});

test("a user cannot revoke another user's token", function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = $other->createToken("Someone else's token");

    actingAsWithConfirmedPassword($user)
        ->delete(route('security.tokens.destroy', $token->accessToken->id))
        ->assertNotFound();

    expect($other->tokens()->count())->toBe(1);
});

test('a non-numeric token id is rejected cleanly instead of erroring', function () {
    $user = User::factory()->create();

    actingAsWithConfirmedPassword($user)
        ->delete('/settings/security/tokens/not-a-number')
        ->assertNotFound();
});

test('a revoked token can no longer authenticate', function () {
    $user = User::factory()->create();
    $token = $user->createToken('My laptop');
    $plainTextToken = $token->plainTextToken;

    actingAsWithConfirmedPassword($user)
        ->from(route('security.edit'))
        ->delete(route('security.tokens.destroy', $token->accessToken->id))
        ->assertRedirect(route('security.edit'));

    // The test application is not rebooted between requests, so the guard would
    // otherwise hand back the user it resolved for the request above.
    $this->app['auth']->forgetGuards();

    $this->withToken($plainTextToken)
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});
