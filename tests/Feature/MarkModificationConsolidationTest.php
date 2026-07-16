<?php

use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use App\Notifications\MarkModificationRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Build a pending modification against a workday that already carries an entry
 * mark, so consolidation has a real mark to rewrite.
 *
 * @param  array<string, mixed>  $overrides
 */
function consolidatableModification(array $overrides = []): MarkModification
{
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);

    $mark = Mark::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'type' => MarkType::In,
        'date_time' => Carbon::today()->setTime(8, 15),
    ]);
    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::today(),
        'mark_in_id' => $mark->id,
    ]);

    return MarkModification::factory()->create(array_merge([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'workday_id' => $workday->id,
        'mark_id' => $mark->id,
        'mark_type' => MarkType::In,
        'status' => MarkModificationStatus::Pending,
        'date_time' => Carbon::today()->setTime(8, 0),
        'original_date_time' => Carbon::today()->setTime(8, 15),
    ], $overrides));
}

test('the command consolidates a modification whose window has closed', function () {
    $modification = consolidatableModification(['notified_at' => now()->subHours(49)]);

    $this->artisan('mark-modifications:approve-overdue')->assertSuccessful();

    $modification->refresh();

    expect($modification->status)->toBe(MarkModificationStatus::Approved)
        ->and($modification->reviewed_at)->not->toBeNull()
        // Silence consolidates without a reviewer — the system applied it.
        ->and($modification->reviewed_by)->toBeNull()
        ->and($modification->mark->refresh()->date_time->format('H:i'))->toBe('08:00');
});

test('the command leaves a modification still inside its window untouched', function () {
    $modification = consolidatableModification(['notified_at' => now()->subHours(10)]);

    $this->artisan('mark-modifications:approve-overdue')->assertSuccessful();

    expect($modification->refresh()->status)->toBe(MarkModificationStatus::Pending)
        ->and($modification->mark->refresh()->date_time->format('H:i'))->toBe('08:15');
});

test('the command ignores already reviewed modifications', function () {
    $modification = consolidatableModification([
        'status' => MarkModificationStatus::Declined,
        'notified_at' => now()->subHours(49),
        'reviewed_at' => now()->subHours(40),
    ]);

    $this->artisan('mark-modifications:approve-overdue')->assertSuccessful();

    expect($modification->refresh()->status)->toBe(MarkModificationStatus::Declined)
        ->and($modification->mark->refresh()->date_time->format('H:i'))->toBe('08:15');
});

test('the window is measured from the notification, not creation', function () {
    // Created long ago, but only notified recently: still within the window.
    $modification = consolidatableModification([
        'created_at' => now()->subDays(5),
        'notified_at' => now()->subHours(2),
    ]);

    $this->artisan('mark-modifications:approve-overdue')->assertSuccessful();

    expect($modification->refresh()->status)->toBe(MarkModificationStatus::Pending);
});

test('sending the request notification stamps notified_at', function () {
    $modification = consolidatableModification();
    expect($modification->notified_at)->toBeNull();

    $modification->user->notify(new MarkModificationRequested($modification));

    expect($modification->refresh()->notified_at)->not->toBeNull();
});
