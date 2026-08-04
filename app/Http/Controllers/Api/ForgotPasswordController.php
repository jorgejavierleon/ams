<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Sends an employee the link that lets them set a new password from their phone.
 *
 * A mobile-only employee who forgets their password has no route back in today:
 * the web console's forgot-password form assumes a browser and a desk (PRD 7.1
 * A4). This is that form's API equivalent, and the link it mails points at the
 * console's existing reset page — the phone's browser handles it, so there is no
 * second reset surface to keep in step with the first.
 *
 * The response never discloses whether the address belongs to an account, and
 * that is the whole reason this endpoint exists rather than the app posting to
 * Fortify's `POST /forgot-password`. Fortify answers an unknown address with a
 * 422 carrying `No podemos encontrar un usuario con esa dirección de correo
 * electrónico.`, which turns a public endpoint into a way to test whether a
 * given person works here.
 */
class ForgotPasswordController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Queued rather than called here, and that is the criterion rather than
        // a performance note. The broker's outcome is deliberately unreadable
        // from the response — every status it can return produces the same 204:
        //
        // - RESET_LINK_SENT — the address has an account and the mail is on its
        //                     way.
        // - INVALID_USER    — it does not. Saying so is the disclosure above.
        // - RESET_THROTTLED — a second request inside the broker's own 60-second
        //                     per-user window (config/auth.php). Reporting it
        //                     would disclose the account just as plainly, since
        //                     an address with no account can never be throttled
        //                     by the broker.
        // - a failure to send — nothing the employee can act on, and reporting
        //                     it for a known address only would disclose again.
        //
        // Making the body uniform is only half of it. Run inline, the known case
        // also costs a token hash, a row and an SMTP handshake that the unknown
        // case does not, and the difference is legible on the clock — so the
        // work goes behind one `jobs` insert, which is identical either way.
        //
        // No `is_active` branch, for the same reason: a deactivated employee may
        // reset their password and still cannot obtain a token (TokenController
        // refuses them), and branching would leak account state through the one
        // response this endpoint is built to keep uniform.
        //
        // Repeated requests are capped by the route's limiter, which counts every
        // request rather than only the ones that resolved to a user, so the 429
        // says nothing either (KMO-14 #5).
        SendPasswordResetLink::dispatch($validated['email']);

        return response()->noContent();
    }
}
