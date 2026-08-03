<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function tokenApiEmployee(): User
{
    return User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
    ]);
}

test('an employee revokes the token their request authenticated with', function () {
    $employee = tokenApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/v1/tokens/current')
        ->assertNoContent();

    expect($employee->tokens()->count())->toBe(0);
});

test('a revoked token no longer authenticates', function () {
    $employee = tokenApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

    // The test application is not rebooted between requests, so the guard would
    // otherwise hand back the user it resolved for the request above.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertUnauthorized();
});

test('signing out on one device leaves the same employee signed in on another', function () {
    $employee = tokenApiEmployee();
    $phoneToken = $employee->createToken('pixel-8')->plainTextToken;
    $tabletToken = $employee->createToken('galaxy-tab')->plainTextToken;

    $this->withToken($phoneToken)
        ->deleteJson('/api/v1/tokens/current')
        ->assertNoContent();

    expect($employee->tokens()->pluck('name')->all())->toBe(['galaxy-tab']);

    $this->app['auth']->forgetGuards();

    $this->withToken($tabletToken)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('id', $employee->id);
});

test('another employee token is untouched by a revocation', function () {
    $employee = tokenApiEmployee();
    $colleague = tokenApiEmployee();

    $employeeToken = $employee->createToken('pixel-8')->plainTextToken;
    $colleagueToken = $colleague->createToken('pixel-8')->plainTextToken;

    $this->withToken($employeeToken)
        ->deleteJson('/api/v1/tokens/current')
        ->assertNoContent();

    $this->app['auth']->forgetGuards();

    $this->withToken($colleagueToken)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('id', $colleague->id);

    expect($colleague->tokens()->count())->toBe(1);
});

test('an unauthenticated revocation returns 401', function () {
    $this->deleteJson('/api/v1/tokens/current')->assertUnauthorized();

    $this->withToken('not-a-real-token')
        ->deleteJson('/api/v1/tokens/current')
        ->assertUnauthorized();
});
