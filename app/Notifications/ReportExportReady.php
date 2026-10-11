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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiryMinutes = (int) config('reports.export.link_expiry_minutes');

        return (new MailMessage)
            ->subject(__('mail.report_export_ready.subject'))
            ->metadata('organization_id', (string) $this->reportExport->organization_id)
            ->markdown('mail.reports.export-ready', [
                'url' => $this->signedShowUrl(),
                'expiryMinutes' => $expiryMinutes,
            ]);
    }

    /**
     * In-app bell payload (KOL-87). Kept generic (kind + a ready-made link)
     * rather than naming the report type, so any future screen that reuses
     * this notification — not just the DT panel — renders in the bell
     * without frontend changes.
     *
     * @return array{kind: string, report_export_id: int, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_export_ready',
            'report_export_id' => $this->reportExport->id,
            'url' => $this->signedShowUrl(),
        ];
    }

    private function signedShowUrl(): string
    {
        $expiryMinutes = (int) config('reports.export.link_expiry_minutes');

        return URL::temporarySignedRoute(
            'dt.reports.exports.show',
            $this->reportExport->expires_at ?? now()->addMinutes($expiryMinutes),
            ['reportExport' => $this->reportExport->id],
        );
    }
}
