<?php

namespace App\Notifications;

use App\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent only from ProcessImportRun::failed() (KOL-102 AC #6): an exception
 * outside per-row handling, after retries are exhausted, so the requester is
 * never left waiting for a completion that never arrives.
 */
class ImportRunFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ImportRun $importRun) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.import_run_failed.subject'))
            ->markdown('mail.imports.run-failed', [
                'url' => route('imports.show', $this->importRun),
            ]);
    }
}
