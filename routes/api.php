<?php

use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\LeavesController;
use App\Http\Controllers\Api\MarkController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\PendingMarkModificationsController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UpcomingShiftsController;
use App\Http\Controllers\Api\WorkdaysController;
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

    // Public, signature-only (KOL-92 / kolvi-mobile KMO-46): the reader
    // opens a document's signed PDF with the OS's own handler via
    // Linking.openURL, so the request reaches an external browser with no
    // Sanctum bearer token and no session at all. Only the signed URL minted
    // by DocumentsController::pdfUrl() authorizes this — no permission gate,
    // no auth guard, on purpose.
    Route::get('me/documents/{document}/pdf', [DocumentsController::class, 'pdfShow'])
        ->middleware('signed')
        ->name('me.documents.pdf');

    Route::middleware('auth:sanctum')->group(function (): void {
        // Sign out on this device only: revokes the bearer token that
        // authenticated the request and leaves the user's other tokens alone.
        Route::delete('tokens/current', [TokenController::class, 'revokeCurrent'])
            ->name('tokens.current.destroy');

        // The mobile app gates its features on the permission names in this payload.
        Route::get('user', function (Request $request): UserResource {
            /** @var User $user */
            $user = $request->user();
            $user->loadMissing(['position', 'premise', 'supervisor']);

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

        // The Jornada tab's Próximos screen: today's shift again plus the
        // schedule after it. Gated, unlike me/today above — there is nothing on
        // this screen for an employee who cannot view their own workday.
        Route::get('me/shifts/upcoming', UpcomingShiftsController::class)
            ->middleware('permission:ViewOwn:Workday')
            ->name('me.shifts.upcoming');

        // The Jornada tab's Historial screen: the employee's own computed
        // workdays over a date range (Res. 38 Art. 22.1 — 5 years, paged back
        // a month at a time by the client rather than a fixed window).
        Route::get('me/workdays', [WorkdaysController::class, 'index'])
            ->middleware('permission:ViewOwn:Workday')
            ->name('me.workdays.index');

        // The Jornada tab's day-detail screen (KMO-34): one workday's shift
        // window and each punch's own mark_id, for the attendance strip and
        // its comprobante links.
        Route::get('me/workdays/{date}', [WorkdaysController::class, 'show'])
            ->middleware('permission:ViewOwn:Workday')
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('me.workdays.show');

        // The Jornada tab's pending-correction card (KMO-35): admin-requested
        // mark corrections awaiting the employee's approve/decline, across
        // every workday rather than one date at a time — the same scope
        // My\WorkdayController::index already surfaces on the web list.
        Route::get('me/mark-modifications', [PendingMarkModificationsController::class, 'index'])
            ->middleware('permission:ReviewOwn:MarkModification')
            ->name('me.mark-modifications.index');
        Route::post('me/workdays/{workday}/modifications/{markModification}/approve', [PendingMarkModificationsController::class, 'approve'])
            ->scopeBindings()
            ->middleware('permission:ReviewOwn:MarkModification')
            ->name('me.workdays.modifications.approve');
        Route::post('me/workdays/{workday}/modifications/{markModification}/decline', [PendingMarkModificationsController::class, 'decline'])
            ->scopeBindings()
            ->middleware('permission:ReviewOwn:MarkModification')
            ->name('me.workdays.modifications.decline');

        // The Permisos tab's Mis solicitudes screen (KMO-39): the employee's
        // own leave requests plus their vacation balance, and cancelling a
        // still-pending one.
        Route::get('me/leaves', [LeavesController::class, 'index'])
            ->middleware('permission:ViewOwn:Leave')
            ->name('me.leaves.index');
        Route::delete('me/leaves/{leave}', [LeavesController::class, 'destroy'])
            ->middleware('permission:CancelOwn:Leave')
            ->name('me.leaves.destroy');

        // The Permisos tab's request wizard (KMO-41): step 1's self-service
        // type and half-day options, the review step's server-computed
        // business-day count, and submitting the request itself.
        Route::get('me/leaves/options', [LeavesController::class, 'options'])
            ->middleware('permission:RequestOwn:Leave')
            ->name('me.leaves.options');
        Route::get('me/leaves/business-days', [LeavesController::class, 'businessDays'])
            ->middleware('permission:RequestOwn:Leave')
            ->name('me.leaves.business-days');
        Route::post('me/leaves', [LeavesController::class, 'store'])
            ->middleware('permission:RequestOwn:Leave')
            ->name('me.leaves.store');

        // The Documentos tab's list (KMO-42): the employee's own non-draft
        // documents — those belonging to them or listing them as a
        // signatory — with a status badge and the awaiting_me flag that
        // drives the pending-signature count and the tab-bar badge, mirroring
        // My\DocumentController::index()'s scope exactly.
        Route::get('me/documents', [DocumentsController::class, 'index'])
            ->middleware('permission:ViewOwn:Document')
            ->name('me.documents.index');

        // The Documentos tab's reader (KMO-43): one document's resolved body
        // plus awaiting_me, driving the sticky Rechazar / Firmar documento bar.
        // Mirrors My\DocumentController::show()'s ownership/signatory
        // authorization exactly.
        Route::get('me/documents/{document}', [DocumentsController::class, 'show'])
            ->middleware('permission:ViewOwn:Document')
            ->name('me.documents.show');

        // Mints the short-lived signed URL the app opens with
        // Linking.openURL (KOL-92 / kolvi-mobile KMO-46): the app calls this
        // first, Sanctum-authenticated, with the same ownership/signatory
        // authorization as show(); the mobile client then hands the
        // resulting URL to the OS unauthenticated, hitting the public
        // me.documents.pdf route above.
        Route::get('me/documents/{document}/pdf-url', [DocumentsController::class, 'pdfUrl'])
            ->middleware('permission:ViewOwn:Document')
            ->name('me.documents.pdf-url');

        // The sign/reject flow behind the reader's sticky Firmar documento /
        // Rechazar buttons (KMO-44/KMO-45): request/resend the verification
        // code, then consume it to author the firma electrónica simple, or
        // reject with an optional reason. Same permission gate as the web
        // self-service portal (routes/web.php), reusing
        // SendVerificationCode/SignDocument/RejectDocument unchanged,
        // mirroring My\DocumentController::sendCode()/sign()/reject().
        Route::post('me/documents/{document}/send-code', [DocumentsController::class, 'sendCode'])
            ->middleware('permission:SignOwn:Document')
            ->name('me.documents.send-code');
        Route::post('me/documents/{document}/sign', [DocumentsController::class, 'sign'])
            ->middleware('permission:SignOwn:Document')
            ->name('me.documents.sign');
        Route::post('me/documents/{document}/reject', [DocumentsController::class, 'reject'])
            ->middleware('permission:SignOwn:Document')
            ->name('me.documents.reject');

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
