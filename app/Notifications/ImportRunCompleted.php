<?php

namespace App\Notifications;

use App\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester once ProcessImportRun (KOL-102) finishes committing
 * every chunk — whether every row succeeded or some errored, since a partial
 * success is still a completion, not a failure (AC #6). ImportRunFailed is
 * the only notification sent from the job's failed() hook instead.
 */
class ImportRunCompleted extends Notification implements ShouldQueue
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
            ->subject(__('mail.import_run_completed.subject'))
            ->markdown('mail.imports.run-completed', [
                'url' => route('imports.show', $this->importRun),
                'createdCount' => $this->importRun->created_count,
                'updatedCount' => $this->importRun->updated_count,
                'skippedCount' => $this->importRun->skipped_count,
                'erroredCount' => $this->importRun->errored_count,
            ]);
    }
}
