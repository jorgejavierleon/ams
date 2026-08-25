<?php

namespace App\Notifications;

use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester when their queued report export could not be
 * generated (KOL-16 AC #8), so they are never left waiting for a
 * notification that never arrives.
 */
class ReportExportFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReportExport $reportExport) {}

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
            ->subject(__('mail.report_export_failed.subject'))
            ->markdown('mail.reports.export-failed');
    }
}
