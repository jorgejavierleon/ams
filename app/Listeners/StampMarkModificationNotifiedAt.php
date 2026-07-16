<?php

namespace App\Listeners;

use App\Notifications\MarkModificationRequested;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Stamp the moment a {@see MarkModificationRequested} notification is actually
 * delivered so the 48h opposition window (Resolución 38, art. 40 c) counts from
 * the email send time rather than from when the request row was created.
 */
class StampMarkModificationNotifiedAt
{
    public function handle(NotificationSent $event): void
    {
        $notification = $event->notification;

        if (! $notification instanceof MarkModificationRequested) {
            return;
        }

        $modification = $notification->markModification;

        if ($modification->notified_at === null) {
            $modification->forceFill(['notified_at' => now()])->save();
        }
    }
}
