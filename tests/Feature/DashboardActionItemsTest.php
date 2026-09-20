<?php

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Enums\MarkType;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The dashboard's "My action items" card (KOL-119.1) renders purely from the
 * shared auth.pendingModificationsCount / auth.pendingSignaturesCount props —
 * the same ones the sidebar badges already use. There is no dashboard-specific
 * query to test; this asserts the two counts reach the dashboard route
 * correctly, which is the data contract the card is built from.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function actionItemsEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create(['organization_id' => $organization->id]);
}

function actionItemsPendingModification(User $employee): MarkModification
{
    $workday = Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    return MarkModification::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'workday_id' => $workday->id,
        'mark_id' => null,
        'mark_type' => MarkType::In,
    ]);
}

function actionItemsPendingSignature(User $employee): DocumentSignature
{
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'body' => '<p>Contrato.</p>',
    ]);

    return DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'type' => DocumentSignatureType::Employee,
        'status' => DocumentSignatureStatus::Pending,
    ]);
}

test('an employee with a pending mark correction sees it reflected on the dashboard', function () {
    $employee = actionItemsEmployee();
    actionItemsPendingModification($employee);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.pendingModificationsCount', 1)
            ->where('auth.pendingSignaturesCount', 0));
});

test('an employee with a document to sign sees it reflected on the dashboard', function () {
    $employee = actionItemsEmployee();
    actionItemsPendingSignature($employee);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.pendingModificationsCount', 0)
            ->where('auth.pendingSignaturesCount', 1));
});

test('an employee with nothing pending sees zero for both counts', function () {
    $employee = actionItemsEmployee();

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.pendingModificationsCount', 0)
            ->where('auth.pendingSignaturesCount', 0));
});
