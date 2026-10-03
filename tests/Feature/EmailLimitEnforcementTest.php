<?php

use App\Mail\DocumentFullySigned;
use App\Models\Document;
use App\Models\EmailLimitCrossing;
use App\Models\EmailSend;
use App\Models\Organization;
use Illuminate\Support\Facades\Mail;

function sendTrackedEmail(Organization $organization): void
{
    $document = Document::factory()->create(['organization_id' => $organization->id]);

    Mail::to('someone@example.com')->send(new DocumentFullySigned($document));
}

test('crossing the soft limit alerts exactly once and keeps sending normally', function () {
    $organization = Organization::factory()->create(['soft_email_limit_override' => 2]);

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2)
        ->and(EmailLimitCrossing::query()
            ->where('organization_id', $organization->id)
            ->where('type', EmailLimitCrossing::TYPE_SOFT)
            ->count())->toBe(1);

    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(3)
        ->and(EmailLimitCrossing::query()
            ->where('organization_id', $organization->id)
            ->where('type', EmailLimitCrossing::TYPE_SOFT)
            ->count())->toBe(1);
});

test('crossing the hard limit suppresses every further send without erroring and alerts exactly once', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 2]);

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2);

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2)
        ->and(EmailLimitCrossing::query()
            ->where('organization_id', $organization->id)
            ->where('type', EmailLimitCrossing::TYPE_HARD)
            ->count())->toBe(1);
});

test('raising the hard limit mid-month immediately resumes sending', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 2]);

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2);

    $organization->hard_email_limit_override = 5;
    $organization->save();

    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(3);
});

test('hard limit suppression lifts automatically on calendar month rollover', function () {
    $this->travelTo(now()->startOfMonth()->addDay());

    $organization = Organization::factory()->create(['hard_email_limit_override' => 1]);

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1);

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDay());

    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2);
});

test('a hard limit of zero means the baseline is unset, not a literal cap, so sending is never suppressed', function () {
    $organization = Organization::factory()->create();

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect($organization->hardEmailLimit())->toBe(0)
        ->and(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2)
        ->and(EmailLimitCrossing::query()->where('organization_id', $organization->id)->count())->toBe(0);
});

test('manually disabling email sending suppresses sends even well under both limits', function () {
    $organization = Organization::factory()->create(['soft_email_limit_override' => 50, 'hard_email_limit_override' => 50]);

    $organization->email_sending_override = false;
    $organization->save();

    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(0);
});

test('manually re-enabling email sending resumes sending even while still over the hard limit', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 1]);

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1);

    $organization->email_sending_override = false;
    $organization->save();

    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1);

    $organization->email_sending_override = true;
    $organization->save();

    sendTrackedEmail($organization);

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2);
});

test('different organizations limits do not interfere with each other', function () {
    $acme = Organization::factory()->create(['hard_email_limit_override' => 1]);
    $beta = Organization::factory()->create(['hard_email_limit_override' => 5]);

    sendTrackedEmail($acme);
    sendTrackedEmail($acme);
    sendTrackedEmail($beta);
    sendTrackedEmail($beta);

    expect(EmailSend::query()->where('organization_id', $acme->id)->count())->toBe(1)
        ->and(EmailSend::query()->where('organization_id', $beta->id)->count())->toBe(2);
});
