<?php

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Enums\DocumentStatus;
use App\Mail\DocumentFullySigned;
use App\Mail\DocumentSignatureVerificationCode;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

/**
 * A document out for signature with a single pending employee signature
 * belonging to $employee, mirroring DocumentSignatureWorkflowTest's own
 * pendingContractFor().
 */
function mobilePendingSignatureFor(User $employee): Document
{
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);

    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'type' => DocumentSignatureType::Employee,
        'status' => DocumentSignatureStatus::Pending,
    ]);

    return $document;
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

// --- Send code: authentication and authorization (#1) ---

test('an unauthenticated request to send a code returns 401', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/send-code")->assertUnauthorized();
});

test('an employee without SignOwn:Document is forbidden from sending a code', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $employee->givePermissionTo('ViewOwn:Document');
    $document = mobilePendingSignatureFor($employee);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/send-code")->assertForbidden();
});

test('a document belonging to another employee who is not a signatory gets 403 when sending a code', function () {
    $employee = mobileDocumentEmployee();
    $other = mobileDocumentEmployee($employee->organization);
    $document = mobilePendingSignatureFor($other);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/send-code")->assertForbidden();
});

test('an unknown document id gets 404 when sending a code', function () {
    $employee = mobileDocumentEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/me/documents/999999/send-code')->assertNotFound();
});

// --- Send code: resend semantics (#2) ---

test('a plain send-code request reuses a live code', function () {
    Mail::fake();

    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $signature = $document->signatures()->first();
    $signature->update([
        'verification_code' => '111111',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/send-code")->assertOk();

    expect($signature->refresh()->verification_code)->toBe('111111');
    Mail::assertNotQueued(DocumentSignatureVerificationCode::class);
});

test('an explicit resend mints and emails a fresh code', function () {
    Mail::fake();

    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $signature = $document->signatures()->first();
    $signature->update([
        'verification_code' => '111111',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/send-code", ['resend' => true])->assertOk();

    expect($signature->refresh()->verification_code)->not->toBe('111111');
    Mail::assertQueued(DocumentSignatureVerificationCode::class);
});

// --- Send code: response shape (#3) ---

test('send-code returns sent true and an expires_at wall-clock string', function () {
    Mail::fake();

    $employee = mobileDocumentEmployee();
    $employee->update(['personal_email' => 'juan@example.com']);
    $document = mobilePendingSignatureFor($employee);
    Sanctum::actingAs($employee);

    $response = $this->postJson("/api/v1/me/documents/{$document->id}/send-code")->assertOk();

    $signature = $document->signatures()->first()->refresh();

    expect($response->json('sent'))->toBeTrue()
        ->and($response->json('expires_at'))->toBe($signature->verification_code_expires_at->format('Y-m-d H:i:s'));
});

test('send-code returns sent false and a null expires_at when the signer has no actionable signature', function () {
    $employee = mobileDocumentEmployee();
    $document = Document::factory()->published()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
    ]);
    Sanctum::actingAs($employee);

    $response = $this->postJson("/api/v1/me/documents/{$document->id}/send-code")->assertOk();

    expect($response->json())->toMatchArray(['sent' => false, 'expires_at' => null]);
});

test('send-code mints nothing when ordered signing blocks the employee turn', function () {
    Mail::fake();

    $employee = mobileDocumentEmployee();
    $legalRep = mobileDocumentEmployee($employee->organization);
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'ordered_signing' => true,
    ]);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $legalRep->id,
        'status' => DocumentSignatureStatus::Pending,
        'order' => 1,
    ]);
    $employeeSignature = DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'status' => DocumentSignatureStatus::Pending,
        'order' => 2,
    ]);
    Sanctum::actingAs($employee);

    $response = $this->postJson("/api/v1/me/documents/{$document->id}/send-code")->assertOk();

    expect($response->json('sent'))->toBeFalse()
        ->and($employeeSignature->refresh()->verification_code)->toBeNull();
    Mail::assertNothingQueued();
});

// --- Sign: authentication and authorization (#4) ---

test('an unauthenticated request to sign returns 401', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/sign", ['code' => '123456'])->assertUnauthorized();
});

test('an employee without SignOwn:Document is forbidden from signing', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $employee->givePermissionTo('ViewOwn:Document');
    $document = mobilePendingSignatureFor($employee);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/sign", ['code' => '123456'])->assertForbidden();
});

test('a document belonging to another employee who is not a signatory gets 403 when signing', function () {
    $employee = mobileDocumentEmployee();
    $other = mobileDocumentEmployee($employee->organization);
    $document = mobilePendingSignatureFor($other);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/sign", ['code' => '123456'])->assertForbidden();
});

test('an unknown document id gets 404 when signing', function () {
    $employee = mobileDocumentEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/me/documents/999999/sign', ['code' => '123456'])->assertNotFound();
});

// --- Sign: invalid code (#5) ---

test('a missing code returns a 422 validation error and does not sign', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $document->signatures()->first()->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/sign")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature)
        ->and($document->signatures()->first()->status)->toBe(DocumentSignatureStatus::Pending);
});

test('a wrong code returns a 422 validation error and does not sign', function () {
    Mail::fake();

    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $document->signatures()->first()->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/sign", ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature)
        ->and($document->signatures()->first()->status)->toBe(DocumentSignatureStatus::Pending);
    Mail::assertNothingQueued();
});

test('an expired code returns a 422 validation error and does not sign', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $document->signatures()->first()->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->subMinute(),
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/sign", ['code' => '654321'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature);
});

// --- Sign: success (#6, #7) ---

test('a correct code signs and returns the signature and document status', function () {
    Mail::fake();

    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $signature = $document->signatures()->first();
    $signature->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);
    Sanctum::actingAs($employee);

    $response = $this->postJson("/api/v1/me/documents/{$document->id}/sign", ['code' => '654321'])->assertOk();

    $signature->refresh();

    expect($response->json())->toMatchArray([
        'status' => 'signed',
        'signed_at' => $signature->signed_at->format('Y-m-d H:i:s'),
        'document_status' => 'signed',
    ]);

    expect($signature->signed_ip)->not->toBeNull()
        ->and($signature->signed_content_hash)->toBe($document->refresh()->contentHash())
        ->and($document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION))->not->toBeNull();

    Mail::assertQueued(DocumentFullySigned::class);
});

test('signing the only-but-not-last signature keeps the document pending and reports its own status', function () {
    Mail::fake();

    $employee = mobileDocumentEmployee();
    $legalRep = mobileDocumentEmployee($employee->organization);
    $document = mobilePendingSignatureFor($employee);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $legalRep->id,
        'type' => DocumentSignatureType::LegalRep,
        'status' => DocumentSignatureStatus::Pending,
    ]);
    $employeeSignature = $document->signatures()->where('user_id', $employee->id)->first();
    $employeeSignature->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);
    Sanctum::actingAs($employee);

    $response = $this->postJson("/api/v1/me/documents/{$document->id}/sign", ['code' => '654321'])->assertOk();

    expect($response->json())->toMatchArray([
        'status' => 'signed',
        'document_status' => 'pending_signature',
    ]);
    Mail::assertNotQueued(DocumentFullySigned::class);
});

// --- Reject: authentication and authorization (#1) ---

test('an unauthenticated request to reject returns 401', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/reject")->assertUnauthorized();
});

test('an employee without SignOwn:Document is forbidden from rejecting', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $employee->givePermissionTo('ViewOwn:Document');
    $document = mobilePendingSignatureFor($employee);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/reject")->assertForbidden();
});

test('a document belonging to another employee who is not a signatory gets 403 when rejecting', function () {
    $employee = mobileDocumentEmployee();
    $other = mobileDocumentEmployee($employee->organization);
    $document = mobilePendingSignatureFor($other);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/reject")->assertForbidden();
});

test('an unknown document id gets 404 when rejecting', function () {
    $employee = mobileDocumentEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/me/documents/999999/reject')->assertNotFound();
});

// --- Reject: validation (#3) ---

test('a reason longer than 500 characters returns a 422 validation error and does not reject', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/reject", ['reason' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature)
        ->and($document->signatures()->first()->status)->toBe(DocumentSignatureStatus::Pending);
});

// --- Reject: no actionable signature (#4) ---

test('a signer with no currently pending signature gets 403 when rejecting', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $document->signatures()->first()->update(['status' => DocumentSignatureStatus::Signed]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/reject")->assertForbidden();

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature);
});

// --- Reject: success (#5, #6) ---

test('rejecting with a reason marks the signature rejected and the document rejected', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $signature = $document->signatures()->first();
    Sanctum::actingAs($employee);

    $response = $this->postJson("/api/v1/me/documents/{$document->id}/reject", ['reason' => 'No estoy de acuerdo.'])
        ->assertOk();

    expect($response->json())->toBe([
        'status' => 'rejected',
        'document_status' => 'rejected',
    ]);

    $signature->refresh();
    expect($signature->status)->toBe(DocumentSignatureStatus::Rejected)
        ->and($signature->rejection_reason)->toBe('No estoy de acuerdo.')
        ->and($signature->signed_ip)->not->toBeNull()
        ->and($document->refresh()->status)->toBe(DocumentStatus::Rejected);
});

test('rejecting with no reason records the rejection with a null reason', function () {
    $employee = mobileDocumentEmployee();
    $document = mobilePendingSignatureFor($employee);
    $signature = $document->signatures()->first();
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/reject")->assertOk();

    expect($signature->refresh()->rejection_reason)->toBeNull()
        ->and($signature->status)->toBe(DocumentSignatureStatus::Rejected);
});

test('rejecting one of several signatories cancels the other still-pending signatures', function () {
    $employee = mobileDocumentEmployee();
    $legalRep = mobileDocumentEmployee($employee->organization);
    $document = mobilePendingSignatureFor($employee);
    $legalRepSignature = DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $legalRep->id,
        'type' => DocumentSignatureType::LegalRep,
        'status' => DocumentSignatureStatus::Pending,
    ]);
    Sanctum::actingAs($employee);

    $this->postJson("/api/v1/me/documents/{$document->id}/reject")->assertOk();

    expect($legalRepSignature->refresh()->status)->toBe(DocumentSignatureStatus::Cancelled)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Rejected);
});
