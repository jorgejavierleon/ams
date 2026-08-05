<?php

use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\MarkController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Middleware\ThrottleTokenIssuance;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The employee mobile app's entire surface. Every route is versioned: the app
 * ships on its own release cycle, so its contract cannot be changed in lockstep
 * with a deploy the way the web frontend's own endpoints can. Internal XHR
 * endpoints the React app calls stay unversioned in routes/web.php.
 */
Route::prefix('v1')->name('v1.')->group(function (): void {
    // Public: exchange employee credentials for a device bearer token. Throttled
    // per email + IP so credential stuffing is capped without one employee's bad
    // attempts locking out their whole premise.
    Route::post('tokens', [TokenController::class, 'issueToken'])
        ->middleware(ThrottleTokenIssuance::class)
        ->name('tokens.store');

    // Public: mail the employee a link to the console's reset page. A mobile-only
    // employee who forgets their password has no other way back in (PRD 7.1 A4).
    // The response is the same 204 whatever the broker decided, so the limiter
    // rather than the response is what caps repetition — and it counts every
    // request, including ones for addresses with no account, so a 429 discloses
    // nothing about who works here either.
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:password-reset-requests')
        ->name('password.email');

    Route::middleware('auth:sanctum')->group(function (): void {
        // Sign out on this device only: revokes the bearer token that
        // authenticated the request and leaves the user's other tokens alone.
        Route::delete('tokens/current', [TokenController::class, 'revokeCurrent'])
            ->name('tokens.current.destroy');

        // The mobile app gates its features on the permission names in this payload.
        Route::get('user', function (Request $request): UserResource {
            /** @var User $user */
            $user = $request->user();

            return new UserResource($user);
        })->name('user.show');

        // Res. 38 Art. 7f: the worker changes their own password, and the
        // confirmation email follows from UserObserver. Throttled at the same
        // 6/minute as the web console's own change (routes/settings.php): the
        // endpoint needs a bearer token, but Sanctum tokens never expire, so
        // whoever holds a stolen phone could otherwise brute-force
        // `current_password` into a full account takeover. Running after
        // `auth:sanctum` makes the throttle signature the employee's id, not the
        // shared premise IP.
        Route::put('user/password', [PasswordController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('user.password.update');

        // The whole home screen in one request: today's shift, the punch state
        // and the week so far. Deliberately ungated — an admin who does not
        // punch still gets the tab, with the punch block omitted rather than a
        // 403 that would break the screen for them.
        Route::get('me/today', TodayController::class)->name('me.today');

        // Mirror the web permission model: clocking needs ClockOwn:Mark, reading
        // needs ViewOwn:Mark.
        Route::post('marks', [MarkController::class, 'store'])
            ->middleware('permission:ClockOwn:Mark')
            ->name('marks.store');

        Route::get('marks', [MarkController::class, 'index'])
            ->middleware('permission:ViewOwn:Mark')
            ->name('marks.index');

        Route::get('marks/{mark}', [MarkController::class, 'show'])
            ->middleware('permission:ViewOwn:Mark')
            ->name('marks.show');
    });
});
