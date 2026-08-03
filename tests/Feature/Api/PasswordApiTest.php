<?php

use App\Mail\AuthProfileUpdated;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** The factory's password is 'password'; every test here changes it from that. */
function passwordApiEmployee(array $attributes = []): User
{
    return User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
        ...$attributes,
    ]);
}

test('an employee changes their own password', function () {
    Mail::fake();

    $employee = passwordApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertNoContent();

    expect(Hash::check('Marcaje-2026!', $employee->fresh()->password))->toBeTrue();
});

test('the new password is the one that issues a token afterwards', function () {
    Mail::fake();

    $employee = passwordApiEmployee(['is_active' => true]);
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertNoContent();

    $this->app['auth']->forgetGuards();

    // The old one stops working and the new one starts: the change reached the
    // credential the app authenticates with, not merely a column.
    $this->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'pixel-8',
    ])->assertStatus(422);

    $this->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => 'Marcaje-2026!',
        'device_name' => 'pixel-8',
    ])->assertOk()->assertJsonStructure(['token']);
});

test('a wrong current password is refused in Spanish and changes nothing', function () {
    Mail::fake();

    $employee = passwordApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'not-the-password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertStatus(422)
        // Res. 38 Art. 5: the app shows this verbatim under the field, so it has
        // to arrive in Spanish rather than be re-translated on the phone.
        ->assertJsonPath('errors.current_password.0', 'La contraseña es incorrecta.');

    expect(Hash::check('password', $employee->fresh()->password))->toBeTrue();

    // Outgoing rather than sent: the mailable is queued, so assertNothingSent
    // would pass even if a refused change had somehow mailed a confirmation.
    Mail::assertNothingOutgoing();
});

test('the current password is checked against the token holder, not the web guard', function () {
    Mail::fake();

    $employee = passwordApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    // The regression this pins: `current_password` with no guard named resolves
    // the default guard. If that ever stops being sanctum on an API request, the
    // rule compares against a null user and every correct password is rejected —
    // so the endpoint would refuse every change with a message saying the
    // employee got their own password wrong.
    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertNoContent();
});

test('a new password failing policy is refused under the password field', function () {
    Mail::fake();

    $employee = passwordApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'corto',
            'password_confirmation' => 'corto',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    expect(Hash::check('password', $employee->fresh()->password))->toBeTrue();
});

test('a mismatched confirmation is refused under the password field', function () {
    Mail::fake();

    $employee = passwordApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2027!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    expect(Hash::check('password', $employee->fresh()->password))->toBeTrue();
});

test('the request is rejected without a token', function () {
    $this->putJson('/api/v1/user/password', [
        'current_password' => 'password',
        'password' => 'Marcaje-2026!',
        'password_confirmation' => 'Marcaje-2026!',
    ])->assertUnauthorized();
});

test('the request is rejected with a revoked token', function () {
    $employee = passwordApiEmployee();
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertUnauthorized();
});

test('a successful change mails the Art. 7f confirmation', function () {
    Mail::fake();

    $employee = passwordApiEmployee(['personal_email' => 'personal@example.com']);
    $token = $employee->createToken('pixel-8')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertNoContent();

    // Asserted rather than assumed. UserObserver sends this, and an
    // implementation reaching for forceFill()->saveQuietly() would skip the
    // observer, drop a compliance obligation and leave no visible symptom.
    //
    // Queued, not sent: AuthProfileUpdated implements ShouldQueue, so it never
    // reaches MailFake's sent bag.
    Mail::assertQueued(AuthProfileUpdated::class, function ($mail) {
        return $mail->hasTo('personal@example.com');
    });
});

test('a successful change revokes the employee other devices and keeps this one', function () {
    Mail::fake();

    $employee = passwordApiEmployee();
    $phone = $employee->createToken('pixel-8')->plainTextToken;
    $employee->createToken('galaxy-tab');
    $employee->createToken('old-phone');

    $this->withToken($phone)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertNoContent();

    expect($employee->tokens()->pluck('name')->all())->toBe(['pixel-8']);

    $this->app['auth']->forgetGuards();

    // The device that made the change is still signed in — KMO-13 #4's first
    // branch, and what keeps an employee from being locked out of punching at
    // the start of a shift.
    $this->withToken($phone)->getJson('/api/v1/user')->assertOk();
});

test('another employee tokens are untouched by a password change', function () {
    Mail::fake();

    $organization = Organization::factory()->create();
    $employee = passwordApiEmployee(['organization_id' => $organization->id]);
    $colleague = passwordApiEmployee(['organization_id' => $organization->id]);

    $token = $employee->createToken('pixel-8')->plainTextToken;
    $colleague->createToken('pixel-7');

    $this->withToken($token)
        ->putJson('/api/v1/user/password', [
            'current_password' => 'password',
            'password' => 'Marcaje-2026!',
            'password_confirmation' => 'Marcaje-2026!',
        ])
        ->assertNoContent();

    expect($colleague->tokens()->pluck('name')->all())->toBe(['pixel-7']);
});
