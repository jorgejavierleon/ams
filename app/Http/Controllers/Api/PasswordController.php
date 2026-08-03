<?php

namespace App\Http\Controllers\Api;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Lets an employee change their own password from the mobile app.
 *
 * Resolución 38 Art. 7f requires a worker-changeable password with an automatic
 * confirmation email. Until this endpoint existed the only way to satisfy it was
 * the web console's settings page, which a mobile-only employee — the person the
 * app is built for — has no practical way to reach.
 */
class PasswordController extends Controller
{
    use PasswordValidationRules;

    /**
     * @throws ValidationException
     */
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            // The guard is named rather than left to default. `auth:sanctum`
            // does call shouldUse('sanctum'), so an unqualified rule happens to
            // resolve the right user today — but it would compare against the
            // web guard's (absent) user the moment that changes, and the failure
            // mode is every correct password being rejected as wrong.
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => $this->passwordRules(),
        ]);

        /** @var User $user */
        $user = $request->user();

        // Every request that reaches here carries a Bearer token, so there is
        // always a real one to spare — the same assumption TokenController's
        // revokeCurrent already makes. Sanctum hands a session-authenticated
        // request a keyless TransientToken instead, but nothing under
        // routes/api.php can be session-authenticated: the api middleware group
        // does not start a session and statefulApi() is not registered in
        // bootstrap/app.php. Registering it would need this line revisited.
        $currentTokenId = $user->currentAccessToken()->getKey();

        // Through update() rather than forceFill()->saveQuietly(): UserObserver
        // watches `password` and mails AuthProfileUpdated, which is the Art. 7f
        // confirmation. Bypassing the observer would drop a compliance
        // obligation while leaving the endpoint looking like it works.
        //
        // `password_changed_at` is deliberately not stamped. It drives
        // hasActivePassword(), which treats null as "no expiry" — writing it
        // here would start a 90-day expiry clock on an employee who never had
        // one, and the web console's own change (Settings\SecurityController)
        // does not write it either.
        $user->update(['password' => $validated['password']]);

        // The employee's other phones and tablets lose their tokens; the device
        // that made this request keeps working. Sanctum tokens are not derived
        // from the password hash, so nothing expires on its own — and someone
        // changing their password because another party has their account needs
        // that party signed out. Keeping the calling device is the other half:
        // bouncing an employee to the login screen at the start of a shift would
        // stop them punching, which is the worse of the two failures.
        $user->tokens()->whereKeyNot($currentTokenId)->delete();

        return response()->noContent();
    }
}
