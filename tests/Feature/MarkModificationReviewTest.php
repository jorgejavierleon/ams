<?php

use App\Enums\MarkModificationStatus;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Workday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Create a pending modification wired to a consistent workday/user/organization.
 *
 * @param  array<string, mixed>  $overrides
 */
function pendingModification(array $overrides = []): MarkModification
{
    $workday = Workday::factory()->create();

    return MarkModification::factory()->create(array_merge([
        'organization_id' => $workday->organization_id,
        'user_id' => $workday->user_id,
        'workday_id' => $workday->id,
        'mark_id' => null,
    ], $overrides));
}

test('the review page renders publicly for a pending modification', function () {
    $modification = pendingModification();

    $this->get("/mark-modifications/{$modification->ulid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mark-modifications/review')
            ->where('modification.ulid', $modification->ulid)
            ->where('modification.state', 'pending')
            ->where('modification.employee_name', $modification->user->name)
        );
});

test('approving adds the mark and closes the request', function () {
    $modification = pendingModification();

    $this->post("/mark-modifications/{$modification->ulid}/approve")
        ->assertRedirect("/mark-modifications/{$modification->ulid}");

    $modification->refresh();

    expect($modification->status)->toBe(MarkModificationStatus::Approved)
        ->and($modification->reviewed_at)->not->toBeNull()
        ->and($modification->mark_id)->not->toBeNull();

    expect($modification->workday->refresh()->mark_in_id)->toBe($modification->mark_id);
});

test('declining closes the request without touching any mark', function () {
    $modification = pendingModification();

    $this->post("/mark-modifications/{$modification->ulid}/decline")
        ->assertRedirect("/mark-modifications/{$modification->ulid}");

    expect($modification->refresh()->status)->toBe(MarkModificationStatus::Declined)
        ->and($modification->reviewed_at)->not->toBeNull()
        ->and(Mark::count())->toBe(0);
});

test('an expired modification shows the expired state and cannot be approved', function () {
    $modification = pendingModification([
        'created_at' => now()->subHours(49),
    ]);

    $this->get("/mark-modifications/{$modification->ulid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('modification.state', 'expired')
        );

    $this->post("/mark-modifications/{$modification->ulid}/approve")
        ->assertRedirect("/mark-modifications/{$modification->ulid}");

    expect($modification->refresh()->status)->toBe(MarkModificationStatus::Pending)
        ->and(Mark::count())->toBe(0);
});

test('an unknown ulid returns 404', function () {
    $this->get('/mark-modifications/does-not-exist')->assertNotFound();
    $this->post('/mark-modifications/does-not-exist/approve')->assertNotFound();
});
