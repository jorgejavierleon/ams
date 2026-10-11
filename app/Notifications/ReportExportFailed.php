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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.report_export_failed.subject'))
            ->metadata('organization_id', (string) $this->reportExport->organization_id)
            ->markdown('mail.reports.export-failed');
    }

    /**
     * In-app bell payload (KOL-87). See {@see ReportExportReady::toArray()}
     * for why this stays generic rather than naming the report type.
     *
     * @return array{kind: string, report_export_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_export_failed',
            'report_export_id' => $this->reportExport->id,
        ];
    }
}
