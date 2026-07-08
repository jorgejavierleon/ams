<?php

namespace App\Observers;

use App\Mail\AuthProfileUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     *
     * Notify the employee at their (previous) personal email whenever a
     * sensitive credential — login email, personal email, or password — is
     * changed, so account takeovers are visible to the account owner.
     */
    public function updated(User $user): void
    {
        if (! $user->wasChanged(['email', 'password', 'personal_email'])) {
            return;
        }

        $recipient = $user->getOriginal('personal_email') ?: $user->getOriginal('email');

        if ($recipient === null) {
            return;
        }

        Mail::to($recipient)->send(new AuthProfileUpdated);
    }
}
