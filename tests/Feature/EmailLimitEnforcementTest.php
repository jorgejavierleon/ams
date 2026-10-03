<?php

use App\Listeners\SuppressEmailOverHardLimit;
use App\Mail\DocumentFullySigned;
use App\Models\Document;
use App\Models\EmailLimitCrossing;
use App\Models\EmailSend;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

// --- KOL-137.5 hardening ---

test('once the hard limit is crossed, a further send skips the monthly count query entirely', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 1]);

    sendTrackedEmail($organization);
    sendTrackedEmail($organization);

    expect(EmailLimitCrossing::query()
        ->where('organization_id', $organization->id)
        ->where('type', EmailLimitCrossing::TYPE_HARD)
        ->exists())->toBeTrue();

    $document = Document::factory()->create(['organization_id' => $organization->id]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    Mail::to('someone@example.com')->send(new DocumentFullySigned($document));

    // Only the Organization lookup (needed for the KOL-137.4 manual-override
    // checks on every send) and the indexed EmailLimitCrossing exists() check
    // remain - no monthly COUNT, no lock, no EmailSend insert.
    expect($queries)->toBe(3)
        ->and(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

test('once the soft limit is crossed, a further send skips the Organization lookup and the monthly count query', function () {
    $organization = Organization::factory()->create([
        'soft_email_limit_override' => 1,
        'hard_email_limit_override' => 100,
        'email_sending_override' => true,
    ]);

    sendTrackedEmail($organization);

    expect(EmailLimitCrossing::query()
        ->where('organization_id', $organization->id)
        ->where('type', EmailLimitCrossing::TYPE_SOFT)
        ->exists())->toBeTrue();

    $document = Document::factory()->create(['organization_id' => $organization->id]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    Mail::to('someone@example.com')->send(new DocumentFullySigned($document));

    expect($queries)->toBe(4)
        ->and(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2);
});

test('the hard-limit check serializes under a per-organization lock, so a held lock blocks a concurrent evaluation', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 1]);
    $lockKey = SuppressEmailOverHardLimit::lockKeyFor($organization->id);

    $heldLock = Cache::lock($lockKey, 10);
    expect($heldLock->get())->toBeTrue();

    // A second evaluation for the same organization cannot acquire the lock
    // while the first is still "in flight" deciding whether to allow a send.
    $contendingLock = Cache::lock($lockKey, 10);
    expect($contendingLock->get())->toBeFalse();

    $heldLock->release();

    expect(Cache::lock($lockKey, 10)->get())->toBeTrue();
});

test('a held lock does not block the send or throw - it decides unlocked instead', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 1]);

    sendTrackedEmail($organization);

    $lock = Cache::lock(SuppressEmailOverHardLimit::lockKeyFor($organization->id), 10);
    expect($lock->get())->toBeTrue();

    // Simulates another concurrent evaluation already holding the lock: this
    // send must still decide - unlocked - rather than blocking on it (and
    // risking a LockTimeoutException deep inside Mailer::shouldSendMessage).
    sendTrackedEmail($organization);

    $lock->release();

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1)
        ->and(EmailLimitCrossing::query()
            ->where('organization_id', $organization->id)
            ->where('type', EmailLimitCrossing::TYPE_HARD)
            ->exists())->toBeTrue();
});

test('an organization without a hard limit override never short-circuits on a recorded crossing, so a higher default limit resumes sending immediately', function () {
    PlatformSetting::current()->update(['expected_emails_per_user_per_month' => 1]);
    $organization = Organization::factory()->create();
    // An explicit user_id, since Document::factory()'s default creates a
    // fresh active employee per call - which would inflate this
    // organization's default limit on every send and defeat the test.
    $employee = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
    $document = Document::factory()->create(['organization_id' => $organization->id, 'user_id' => $employee->id]);

    Mail::to('someone@example.com')->send(new DocumentFullySigned($document));
    Mail::to('someone@example.com')->send(new DocumentFullySigned($document));

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(1)
        ->and(EmailLimitCrossing::query()
            ->where('organization_id', $organization->id)
            ->where('type', EmailLimitCrossing::TYPE_HARD)
            ->exists())->toBeTrue();

    // Raises the default (computed) limit without touching
    // hard_email_limit_override, so OrganizationObserver never fires - this
    // only works because the short-circuit itself never applies here.
    User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);

    Mail::to('someone@example.com')->send(new DocumentFullySigned($document));

    expect(EmailSend::query()->where('organization_id', $organization->id)->count())->toBe(2);
});

test('hasCrossedEmailLimitThisMonth and recordEmailLimitCrossing operate on the given month, not the wall-clock month', function () {
    $organization = Organization::factory()->create();
    $january = Carbon::parse('2026-01-15');
    $february = Carbon::parse('2026-02-15');

    $organization->recordEmailLimitCrossing(EmailLimitCrossing::TYPE_HARD, $january);

    expect($organization->hasCrossedEmailLimitThisMonth(EmailLimitCrossing::TYPE_HARD, $january))->toBeTrue()
        ->and($organization->hasCrossedEmailLimitThisMonth(EmailLimitCrossing::TYPE_HARD, $february))->toBeFalse();
});

test('raising the hard limit mid-month clears the recorded crossing for the current month', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 1]);
    $organization->recordEmailLimitCrossing(EmailLimitCrossing::TYPE_HARD, now());

    expect($organization->hasCrossedEmailLimitThisMonth(EmailLimitCrossing::TYPE_HARD, now()))->toBeTrue();

    $organization->hard_email_limit_override = 5;
    $organization->save();

    expect($organization->hasCrossedEmailLimitThisMonth(EmailLimitCrossing::TYPE_HARD, now()))->toBeFalse();
});

test('saving an organization without changing its hard limit leaves a recorded crossing alone', function () {
    $organization = Organization::factory()->create(['hard_email_limit_override' => 1]);
    $organization->recordEmailLimitCrossing(EmailLimitCrossing::TYPE_HARD, now());

    $organization->soft_email_limit_override = 10;
    $organization->save();

    expect($organization->hasCrossedEmailLimitThisMonth(EmailLimitCrossing::TYPE_HARD, now()))->toBeTrue();
});
