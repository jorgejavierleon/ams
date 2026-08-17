<?php

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function reviewerEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
    ]);
}

function pendingModificationFor(User $employee, ?string $date = null): MarkModification
{
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => $date ?? now()->toDateString(),
    ]);

    $mark = Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => MarkType::In,
        'date_time' => Carbon::parse('2026-08-10 08:00:00'),
    ]);

    $requester = User::factory()->create(['organization_id' => $employee->organization_id, 'name' => 'Ana Pérez']);

    return MarkModification::factory()->create([
        'organization_id' => $employee->organization_id,
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
        'mark_id' => $mark->id,
        'mark_type' => MarkType::In,
        'reason' => MarkModificationReason::MarkForgotten,
        'date_time' => Carbon::parse('2026-08-10 08:32:00'),
        'created_by' => $requester->id,
    ]);
}

// --- Authentication and authorization ---

test('an unauthenticated request for pending corrections returns 401', function () {
    $this->getJson('/api/v1/me/mark-modifications')->assertUnauthorized();
});

test('an employee without ReviewOwn:MarkModification is forbidden', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/mark-modifications')->assertForbidden();
});

// --- Listing (#1, #2, #3) ---

test('only the authenticated employee own pending corrections are returned, newest first', function () {
    $employee = reviewerEmployee();
    $other = reviewerEmployee($employee->organization);

    $older = pendingModificationFor($employee, '2026-08-01');
    $older->update(['created_at' => now()->subDay()]);
    $newer = pendingModificationFor($employee, '2026-08-02');
    pendingModificationFor($other);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/mark-modifications')->assertOk();

    expect($response->json())->toHaveCount(2)
        ->and($response->json('0.id'))->toBe($newer->id)
        ->and($response->json('1.id'))->toBe($older->id);
});

test('an employee with no pending corrections gets an empty array', function () {
    $employee = reviewerEmployee();
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/mark-modifications')->assertOk();

    expect($response->json())->toBe([]);
});

test('an approved or declined modification is not listed as pending', function () {
    $employee = reviewerEmployee();
    $modification = pendingModificationFor($employee);
    $modification->update(['status' => MarkModificationStatus::Approved]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/mark-modifications')->assertOk();

    expect($response->json())->toBe([]);
});

test('each entry carries the fields the correction card needs', function () {
    $employee = reviewerEmployee();
    $modification = pendingModificationFor($employee);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/mark-modifications')->assertOk();

    expect($response->json('0'))->toMatchArray([
        'id' => $modification->id,
        'workday_id' => $modification->workday_id,
        'original_time' => '08:00',
        'proposed_time' => '08:32',
        'requested_by' => 'Ana Pérez',
    ])
        ->and($response->json('0.mark_type_label'))->not->toBeEmpty()
        ->and($response->json('0.reason'))->not->toBeEmpty()
        ->and($response->json('0.expires_at'))->toBe(
            $modification->created_at->copy()->addHours((int) config('ams.mark_modification_timeout_hours'))->format('Y-m-d H:i:s')
        );
});

// --- Approve / decline (#4, #5, #6, #7) ---

test('the owning employee can approve a pending correction', function () {
    $employee = reviewerEmployee();
    $modification = pendingModificationFor($employee);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/workdays/{$modification->workday_id}/modifications/{$modification->id}/approve")
        ->assertNoContent();

    expect($modification->fresh()->status)->toBe(MarkModificationStatus::Approved);
});

test('the owning employee can decline a pending correction', function () {
    $employee = reviewerEmployee();
    $modification = pendingModificationFor($employee);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/workdays/{$modification->workday_id}/modifications/{$modification->id}/decline")
        ->assertNoContent();

    expect($modification->fresh()->status)->toBe(MarkModificationStatus::Declined);
});

test('another employee cannot approve a correction that is not theirs', function () {
    $employee = reviewerEmployee();
    $modification = pendingModificationFor($employee);
    $other = reviewerEmployee($employee->organization);
    Sanctum::actingAs($other);

    $this->postJson("/api/v1/me/workdays/{$modification->workday_id}/modifications/{$modification->id}/approve")
        ->assertForbidden();

    expect($modification->fresh()->status)->toBe(MarkModificationStatus::Pending);
});

test('an already-reviewed modification cannot be approved again', function () {
    $employee = reviewerEmployee();
    $modification = pendingModificationFor($employee);
    $modification->update(['status' => MarkModificationStatus::Declined]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/workdays/{$modification->workday_id}/modifications/{$modification->id}/approve")
        ->assertForbidden();
});

test('an expired modification cannot be approved or declined', function () {
    $employee = reviewerEmployee();
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    $modification = MarkModification::factory()->overdue()->create([
        'organization_id' => $employee->organization_id,
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/workdays/{$modification->workday_id}/modifications/{$modification->id}/approve")
        ->assertForbidden();
    $this->postJson("/api/v1/me/workdays/{$modification->workday_id}/modifications/{$modification->id}/decline")
        ->assertForbidden();

    expect($modification->fresh()->status)->toBe(MarkModificationStatus::Pending);
});

test('a modification id that does not belong to the given workday 404s', function () {
    $employee = reviewerEmployee();
    $modification = pendingModificationFor($employee);
    $otherWorkday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->subDay()->toDateString(),
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/workdays/{$otherWorkday->id}/modifications/{$modification->id}/approve")
        ->assertNotFound();
});
