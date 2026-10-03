<?php

use App\Mail\DocumentFullySigned;
use App\Mail\DtAuditNotification;
use App\Mail\SendDtPassword;
use App\Models\Document;
use App\Models\EmailSend;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\LeaveApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('sending a tracked mailable records an email send scoped to its organization', function () {
    $organization = Organization::factory()->create();
    $document = Document::factory()->create(['organization_id' => $organization->id]);

    Mail::to('someone@example.com')->send(new DocumentFullySigned($document));

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

test('sending a tracked notification records an email send scoped to its organization', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $leave = Leave::factory()->create(['organization_id' => $organization->id, 'user_id' => $employee->id]);

    $employee->notify(new LeaveApproved($leave));

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

test('a send to a personal_email still attributes to the right organization, not the recipient', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'personal_email' => 'personal@example.com',
    ]);
    $document = Document::factory()->create(['organization_id' => $organization->id]);

    Mail::to($employee->personal_email)->send(new DocumentFullySigned($document));

    $send = EmailSend::query()->sole();
    expect($send->organization_id)->toBe($organization->id);
});

test('different organizations counts do not mix', function () {
    $acme = Organization::factory()->create();
    $beta = Organization::factory()->create();
    $acmeDocument = Document::factory()->create(['organization_id' => $acme->id]);
    $betaDocument = Document::factory()->create(['organization_id' => $beta->id]);

    Mail::to('a@example.com')->send(new DocumentFullySigned($acmeDocument));
    Mail::to('b@example.com')->send(new DocumentFullySigned($betaDocument));
    Mail::to('c@example.com')->send(new DocumentFullySigned($betaDocument));

    expect(EmailSend::query()->where('organization_id', $acme->id)->count())->toBe(1)
        ->and(EmailSend::query()->where('organization_id', $beta->id)->count())->toBe(2);
});

test('DT audit mail is never recorded against any organization', function () {
    $organization = Organization::factory()->create(['email' => 'contact@acme.test']);

    Mail::to($organization->email)->send(new DtAuditNotification);

    expect(EmailSend::query()->count())->toBe(0);
});

test('DT password mail is never recorded against any organization', function () {
    $dtUser = User::factory()->create(['is_dt' => true, 'organization_id' => null]);

    Mail::to($dtUser)->send(new SendDtPassword('a-temporary-password'));

    expect(EmailSend::query()->count())->toBe(0);
});
