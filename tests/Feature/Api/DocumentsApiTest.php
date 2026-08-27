<?php

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function mobileDocumentEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
    ]);
}

// --- Authentication and authorization ---

test('an unauthenticated request for documents returns 401', function () {
    $this->getJson('/api/v1/me/documents')->assertUnauthorized();
});

test('an employee without ViewOwn:Document is forbidden', function () {
    $employee = User::factory()->create(); // no role, no permissions
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/documents')->assertForbidden();
});

// --- Scope (#1, #4) ---

test('only the employee own documents and ones they sign are returned, drafts excluded', function () {
    $employee = mobileDocumentEmployee();
    $other = mobileDocumentEmployee($employee->organization);

    $own = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    $asSignatory = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $other->id,
    ]);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $asSignatory->id,
        'user_id' => $employee->id,
    ]);

    // Own draft: excluded by status regardless of ownership.
    Document::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'status' => DocumentStatus::Draft,
    ]);

    // Belongs to someone else and the employee is not a signatory: excluded.
    Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $other->id,
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/documents')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$own->id, $asSignatory->id])->sort()->values()->all());
});

test('results are ordered by published_at desc then id desc, newest first', function () {
    $employee = mobileDocumentEmployee();

    $older = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'published_at' => '2026-08-01',
    ]);
    $newer = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'published_at' => '2026-08-15',
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/documents')->assertOk();

    expect($response->json('data.0.id'))->toBe($newer->id)
        ->and($response->json('data.1.id'))->toBe($older->id);
});

test('an employee with no visible documents gets an empty array', function () {
    $employee = mobileDocumentEmployee();
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/documents')->assertOk();

    expect($response->json('data'))->toBe([]);
});

// --- Field shape (#2, #3) ---

test('each entry carries the fields the mobile Documentos tab needs', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'title' => 'Reglamento interno',
        'published_at' => '2026-08-10',
    ]);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/documents')->assertOk();

    expect($response->json('data.0'))->toMatchArray([
        'id' => $document->id,
        'title' => 'Reglamento interno',
        'status' => 'published',
        'status_label' => $document->status->label(),
        'status_badge' => 'success',
        'published_at' => '2026-08-10',
        'my_signature' => null,
        'awaiting_me' => false,
    ]);
});

test('the response is a bare data envelope with no pagination metadata', function () {
    $employee = mobileDocumentEmployee();
    Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/documents')->assertOk();

    expect($response->json())->toHaveKey('data')
        ->and($response->json())->not->toHaveKey('meta')
        ->and($response->json())->not->toHaveKey('links');
});

// --- awaiting_me (#2) ---

test('awaiting_me is true for a pending signature that is the employee turn', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    $signature = DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'status' => DocumentSignatureStatus::Pending,
    ]);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/documents')->assertOk();

    expect($response->json('data.0.awaiting_me'))->toBeTrue()
        ->and($response->json('data.0.my_signature'))->toMatchArray([
            'status' => 'pending',
            'status_badge' => $signature->status->badge(),
        ]);
});

test('awaiting_me is false when ordered signing blocks the employee turn', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'ordered_signing' => true,
    ]);

    // A lower-order signatory has not signed yet, so the employee's own
    // pending signature is not actionable.
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => mobileDocumentEmployee($employee->organization)->id,
        'status' => DocumentSignatureStatus::Pending,
        'order' => 1,
    ]);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'status' => DocumentSignatureStatus::Pending,
        'order' => 2,
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/me/documents')->assertOk();

    expect($response->json('data.0.awaiting_me'))->toBeFalse();
});

// --- Show: authentication and authorization ---

test('an unauthenticated request for a document detail returns 401', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    $this->getJson("/api/v1/me/documents/{$document->id}")->assertUnauthorized();
});

test('an employee without ViewOwn:Document is forbidden from a document detail', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]); // no role, no permissions
    $document = Document::factory()->published()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->getJson("/api/v1/me/documents/{$document->id}")->assertForbidden();
});

test('a document belonging to another employee who is not a signatory gets 403 (#4)', function () {
    $employee = mobileDocumentEmployee();
    $other = mobileDocumentEmployee($employee->organization);
    $document = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $other->id,
    ]);
    Sanctum::actingAs($employee);

    $this->getJson("/api/v1/me/documents/{$document->id}")->assertForbidden();
});

test('a document id that does not exist gets 404 (#1)', function () {
    $employee = mobileDocumentEmployee();
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/me/documents/999999')->assertNotFound();
});

test('the employee own document is visible (#1)', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->getJson("/api/v1/me/documents/{$document->id}")->assertOk();
});

test('a document the employee is a signatory on, but does not own, is visible (#1)', function () {
    $employee = mobileDocumentEmployee();
    $other = mobileDocumentEmployee($employee->organization);
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $other->id,
    ]);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $this->getJson("/api/v1/me/documents/{$document->id}")->assertOk();
});

// --- Show: field shape (#2, #3) ---

test('the document detail carries the resolved body and status badge (#2)', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'title' => 'Reglamento interno',
        'body' => 'Hola {{employee_name}}',
        'published_at' => '2026-08-10',
    ]);
    Sanctum::actingAs($employee);

    $response = $this->getJson("/api/v1/me/documents/{$document->id}")->assertOk();

    expect($response->json())->toMatchArray([
        'id' => $document->id,
        'title' => 'Reglamento interno',
        'status_label' => $document->status->label(),
        'status_badge' => 'success',
        'body' => 'Hola '.$employee->name,
        'published_at' => '2026-08-10',
        'awaiting_me' => false,
    ]);
});

test('the document detail response is a bare object, not a data envelope (#3)', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $response = $this->getJson("/api/v1/me/documents/{$document->id}")->assertOk();

    expect($response->json())->toHaveKey('id')
        ->and($response->json())->not->toHaveKey('data');
});

test('awaiting_me is true for the document detail when it is the employee turn (#2)', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'status' => DocumentSignatureStatus::Pending,
    ]);
    Sanctum::actingAs($employee);

    $response = $this->getJson("/api/v1/me/documents/{$document->id}")->assertOk();

    expect($response->json('awaiting_me'))->toBeTrue();
});
