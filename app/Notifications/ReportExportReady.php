<?php

namespace App\Notifications;

use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the requester once their queued report export has finished
 * rendering (KOL-16 AC #3), with a signed, expiring download link.
 */
class ReportExportReady extends Notification implements ShouldQueue
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
        $expiryMinutes = (int) config('reports.export.link_expiry_minutes');

        $url = URL::temporarySignedRoute(
            'dt.reports.exports.show',
            $this->reportExport->expires_at ?? now()->addMinutes($expiryMinutes),
            ['reportExport' => $this->reportExport->id],
        );

        return (new MailMessage)
            ->subject(__('mail.report_export_ready.subject'))
            ->markdown('mail.reports.export-ready', [
                'url' => $url,
                'expiryMinutes' => $expiryMinutes,
            ]);
    }
}
