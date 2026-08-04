<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;

/**
 * Mails the password-reset link, off the request.
 *
 * Queued for a reason the mail itself does not need: the endpoint in front of
 * this answers 204 whether or not the address belongs to an account (KMO-14 #2),
 * and that promise is only kept if the two cases also *cost* the same. Run
 * inline, `sendResetLink()` hashes a token, writes a `password_reset_tokens`
 * row and hands a message to the mailer for an address that exists, and returns
 * from a `SELECT` that found nothing for one that does not — a difference an
 * attacker reads off the clock instead of the body. Against a local Mailpit that
 * was ~245ms versus ~232ms; against a real SMTP provider it is far wider.
 *
 * Dispatching moves all of it behind one `jobs` insert, which is the same work
 * for both. The controller then does exactly as much for an address nobody owns
 * as for the CEO's.
 *
 * The broker's own decisions are unchanged and still invisible: an unknown
 * address resolves to nothing here, and a second request inside its 60-second
 * window (config/auth.php) is refused here, where nobody is waiting on it.
 */
class SendPasswordResetLink implements ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public readonly string $email) {}

    public function handle(): void
    {
        Password::broker()->sendResetLink(['email' => $this->email]);
    }
}
