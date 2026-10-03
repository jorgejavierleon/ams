<?php

use App\Mail\AuthProfileUpdated;
use App\Mail\DocumentFullySigned;
use App\Mail\DocumentSignatureVerificationCode;
use App\Mail\DtAuditNotification;
use App\Mail\MarkCreated;
use App\Mail\SendDtPassword;
use App\Models\Document;
use App\Models\ImportRun;
use App\Models\Leave;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\OvertimePact;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRestDayBalance;
use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\DocumentSignatureRequested;
use App\Notifications\ImportRunCompleted;
use App\Notifications\ImportRunFailed;
use App\Notifications\LeaveApproved;
use App\Notifications\LeaveRejected;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\MarkModificationRequested;
use App\Notifications\OvertimePactNearingExpiry;
use App\Notifications\OvertimeRequestApproved;
use App\Notifications\OvertimeRequestRejected;
use App\Notifications\OvertimeRequestSubmitted;
use App\Notifications\ReportExportFailed;
use App\Notifications\ReportExportReady;
use App\Notifications\RestDayBalanceAccrued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * Guards KOL-137.1 AC #7: every class in App\Mail and App\Notifications
 * (other than the DT-excluded ones) must declare organization_id metadata,
 * so a future mail class added without it fails here rather than silently
 * never being counted. The directory scan is the actual enumeration; the
 * $accountedFor map below must be kept in sync with it, and it's that map's
 * instances whose Envelope/MailMessage metadata is asserted.
 */
test('every tracked mail/notification class declares organization_id metadata', function () {
    $excluded = [
        DtAuditNotification::class,
        SendDtPassword::class,
    ];

    $organization = Organization::factory()->create();
    $user = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $document = Document::factory()->create(['organization_id' => $organization->id]);
    $mark = Mark::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $leave = Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $importRun = ImportRun::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $markModification = MarkModification::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $overtimePact = OvertimePact::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $overtimeRequest = OvertimeRequest::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $reportExport = ReportExport::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $restDayBalances = new Collection([
        OvertimeRestDayBalance::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]),
    ]);

    /** @var array<class-string, Envelope|MailMessage> $accountedFor */
    $accountedFor = [
        AuthProfileUpdated::class => (new AuthProfileUpdated($user))->envelope(),
        DocumentFullySigned::class => (new DocumentFullySigned($document))->envelope(),
        DocumentSignatureVerificationCode::class => (new DocumentSignatureVerificationCode($document, '123456'))->envelope(),
        MarkCreated::class => (new MarkCreated($mark))->envelope(),

        DocumentSignatureRequested::class => (new DocumentSignatureRequested($document))->toMail($user),
        ImportRunCompleted::class => (new ImportRunCompleted($importRun))->toMail($user),
        ImportRunFailed::class => (new ImportRunFailed($importRun))->toMail($user),
        LeaveApproved::class => (new LeaveApproved($leave))->toMail($user),
        LeaveRejected::class => (new LeaveRejected($leave))->toMail($user),
        LeaveRequestSubmitted::class => (new LeaveRequestSubmitted($leave))->toMail($user),
        MarkModificationRequested::class => (new MarkModificationRequested($markModification))->toMail($user),
        OvertimePactNearingExpiry::class => (new OvertimePactNearingExpiry($overtimePact))->toMail($user),
        OvertimeRequestApproved::class => (new OvertimeRequestApproved($overtimeRequest))->toMail($user),
        OvertimeRequestRejected::class => (new OvertimeRequestRejected($overtimeRequest))->toMail($user),
        OvertimeRequestSubmitted::class => (new OvertimeRequestSubmitted($overtimeRequest))->toMail($user),
        ReportExportFailed::class => (new ReportExportFailed($reportExport))->toMail($user),
        ReportExportReady::class => (new ReportExportReady($reportExport))->toMail($user),
        RestDayBalanceAccrued::class => (new RestDayBalanceAccrued($restDayBalances))->toMail($user),
    ];

    $declaredOnDisk = collect(File::files(app_path('Mail')))
        ->map(fn ($file) => 'App\\Mail\\'.$file->getFilenameWithoutExtension())
        ->merge(
            collect(File::files(app_path('Notifications')))
                ->map(fn ($file) => 'App\\Notifications\\'.$file->getFilenameWithoutExtension())
        )
        ->sort()
        ->values();

    $expected = collect([...array_keys($accountedFor), ...$excluded])->sort()->values();

    expect($declaredOnDisk->all())->toBe($expected->all());

    foreach ($accountedFor as $class => $metadataCarrier) {
        expect($metadataCarrier->metadata)->toHaveKey('organization_id');
    }
});
