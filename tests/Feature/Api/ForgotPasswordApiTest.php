<?php

use App\Jobs\SendPasswordResetLink;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function forgotPasswordEmployee(array $attributes = []): User
{
    return User::factory()->employee()->create([
        'organization_id' => Organization::factory()->create()->id,
        ...$attributes,
    ]);
}

function requestResetLink(string $email): TestResponse
{
    return test()->postJson('/api/v1/forgot-password', ['email' => $email]);
}

test('an employee is mailed a reset link', function () {
    Notification::fake();

    $employee = forgotPasswordEmployee();

    requestResetLink($employee->email)->assertNoContent();

    Notification::assertSentTo($employee, ResetPassword::class);
});

// The criterion this endpoint exists for (KMO-14 #2). Fortify's own
// POST /forgot-password answers an unknown address with a 422 carrying
// `No podemos encontrar un usuario…`, which turns a public endpoint into a way
// to test whether a given person works here.
test('an unknown address gets the identical response and no mail', function () {
    Notification::fake();

    $known = forgotPasswordEmployee();

    $forKnown = requestResetLink($known->email);
    $forUnknown = requestResetLink('nadie-trabaja-aqui@example.com');

    $forUnknown->assertNoContent();

    // Byte-identical, not merely both-2xx: a difference in status or body is a
    // difference an attacker can read.
    expect($forUnknown->getStatusCode())->toBe($forKnown->getStatusCode());
    expect($forUnknown->getContent())->toBe($forKnown->getContent());

    Notification::assertSentTo($known, ResetPassword::class);
    Notification::assertCount(1);
});

// The other half of #2, and the one a body comparison cannot reach. A uniform
// 204 is still a disclosure if the two cases take visibly different times to
// produce it — run inline, the known address costs a token hash, a row and an
// SMTP handshake that the unknown one does not. Everything the broker decides
// happens in the job, so the request itself is one `jobs` insert either way.
test('the request does identical work whether or not the address has an account', function () {
    Queue::fake();
    Notification::fake();

    $employee = forgotPasswordEmployee();

    requestResetLink($employee->email)->assertNoContent();
    requestResetLink('nadie-trabaja-aqui@example.com')->assertNoContent();

    Queue::assertPushed(SendPasswordResetLink::class, 2);
    Queue::assertPushed(
        SendPasswordResetLink::class,
        fn (SendPasswordResetLink $job) => $job->email === $employee->email,
    );

    // With the queue faked the job never runs, so this is the assertion that the
    // broker was not reached inside the request: had it been, the known address
    // would have produced a notification right here.
    Notification::assertNothingSent();
});

// The broker refuses a second link for the same user inside its own 60-second
// window (config/auth.php `throttle`) and returns RESET_THROTTLED. Reporting
// that would disclose the account just as plainly as INVALID_USER: an address
// with no account can never be throttled by the broker.
test('the broker throttle is invisible from outside', function () {
    Notification::fake();

    $employee = forgotPasswordEmployee();

    requestResetLink($employee->email)->assertNoContent();

    $second = requestResetLink($employee->email);
    $unknown = requestResetLink('nadie-trabaja-aqui@example.com');

    expect($second->getStatusCode())->toBe($unknown->getStatusCode());
    expect($second->getContent())->toBe($unknown->getContent());

    Notification::assertCount(1);
});

// A deactivated employee resetting their password is harmless — TokenController
// still refuses them — and branching on `is_active` would leak account state
// through the one response this endpoint keeps uniform.
test('a deactivated employee is not treated differently', function () {
    Notification::fake();

    $employee = forgotPasswordEmployee(['is_active' => false]);

    requestResetLink($employee->email)->assertNoContent();

    Notification::assertSentTo($employee, ResetPassword::class);
});

test('a malformed address is a validation error', function () {
    Notification::fake();

    // Not a disclosure: the address is rejected for its shape, which is knowable
    // without a database.
    test()->postJson('/api/v1/forgot-password', ['email' => 'no-es-un-correo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    test()->postJson('/api/v1/forgot-password', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Notification::assertNothingSent();
});

// KMO-14 #5. The app reads the 429 and its Retry-After, counts it down and holds
// the submit control; without this the endpoint would be a way to flood one
// mailbox, and repeated taps would look like nothing happening.
test('the fourth request for one address in a minute is throttled', function () {
    Notification::fake();

    $employee = forgotPasswordEmployee();

    foreach (range(1, 3) as $attempt) {
        requestResetLink($employee->email)->assertNoContent();
    }

    requestResetLink($employee->email)
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('the limiter is keyed per address, so one employee does not block another', function () {
    Notification::fake();

    $employee = forgotPasswordEmployee();
    $colleague = forgotPasswordEmployee();

    foreach (range(1, 3) as $attempt) {
        requestResetLink($employee->email)->assertNoContent();
    }

    requestResetLink($employee->email)->assertStatus(429);

    // Same IP, different address: employees at one premise share a NAT, and a
    // limiter keyed on the IP alone would take the whole premise down with one
    // person's repeated taps.
    requestResetLink($colleague->email)->assertNoContent();
});

// KMO-14 #4, the half this side owns: the token that was mailed is the token the
// console's reset page accepts, and the password it sets is the one that issues
// a mobile token afterwards.
test('the mailed token resets the password and the new one signs in', function () {
    Notification::fake();

    $employee = forgotPasswordEmployee(['is_active' => true]);

    requestResetLink($employee->email)->assertNoContent();

    $token = null;

    Notification::assertSentTo($employee, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    expect(Password::broker()->reset([
        'email' => $employee->email,
        'password' => 'Marcaje-2026!',
        'password_confirmation' => 'Marcaje-2026!',
        'token' => $token,
    ], function (User $user, string $password) {
        $user->forceFill(['password' => $password])->save();
    }))->toBe(Password::PASSWORD_RESET);

    expect(Hash::check('Marcaje-2026!', $employee->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/tokens', [
        'email' => $employee->email,
        'password' => 'Marcaje-2026!',
        'device_name' => 'pixel-8',
    ])->assertOk()->assertJsonStructure(['token']);
});

// Res. 38 Art. 5. Laravel builds this email from English sentences used as their
// own translation keys, and this app has no `lang/es.json` — so without the
// `ResetPassword::toMailUsing` callback in FortifyServiceProvider it sends
// English to an employee the mobile app has just told, in Spanish, to go and
// read it.
//
// The assertion goes through `toMail()` rather than reading the catalogue,
// which is what makes it fail if that callback is ever removed: the class being
// sent is still the framework's, so nothing else here would notice.
test('the reset email is in Spanish and links to the console reset page', function () {
    Notification::fake();

    $employee = forgotPasswordEmployee();

    requestResetLink($employee->email)->assertNoContent();

    Notification::assertSentTo($employee, ResetPassword::class, function (ResetPassword $notification) use ($employee) {
        $mail = $notification->toMail($employee);
        $rendered = (string) $mail->render();

        expect($mail->subject)->toBe('Restablece tu contraseña');
        expect($rendered)->toContain('Crear una contraseña nueva');
        expect($rendered)->toContain('El enlace vence en 60 minutos.');
        expect($rendered)->not->toContain('Reset Password');

        // The link is the console's own page, which is what the phone's browser
        // opens — there is no second reset surface for this to drift from.
        expect($rendered)->toContain(route('password.reset', [
            'token' => $notification->token,
            'email' => $employee->email,
        ], false));

        return true;
    });
});
