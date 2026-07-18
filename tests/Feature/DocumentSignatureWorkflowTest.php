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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $employeeRole = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    foreach (['ViewOwn:Document', 'SignOwn:Document'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $employeeRole->givePermissionTo(['ViewOwn:Document', 'SignOwn:Document']);
});

/**
 * An employee attached to a real organization so documents scope correctly.
 */
function signingEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create(['organization_id' => $organization->id]);
}

/**
 * A signable document already out for signature, with a single pending
 * employee signature belonging to $employee.
 */
function pendingContractFor(User $employee): Document
{
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'body' => '<p>Contrato.</p>',
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

// --- Access control ---

test('a non-signatory cannot view a document in the panel', function () {
    $employee = signingEmployee();
    $document = pendingContractFor($employee);

    $other = signingEmployee($employee->organization);

    $this->actingAs($other)->get(route('my.documents.show', $document))->assertForbidden();
});

test('the panel lists the employee own published documents', function () {
    $employee = signingEmployee();
    pendingContractFor($employee);

    $this->actingAs($employee)
        ->get(route('my.documents.index'))
        ->assertOk();
});

test('the pending signatures count is shared for the nav badge', function () {
    $employee = signingEmployee();
    pendingContractFor($employee);
    pendingContractFor($employee);

    $this->actingAs($employee)
        ->get(route('my.documents.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.pendingSignaturesCount', 2));
});

// --- Verification code ---

test('requesting a code stores it and mails it to the personal email', function () {
    Mail::fake();

    $employee = signingEmployee();
    $employee->update(['personal_email' => 'juan@example.com']);
    $document = pendingContractFor($employee);

    $this->actingAs($employee)
        ->post(route('my.documents.send-code', $document))
        ->assertRedirect();

    $signature = $document->signatures()->where('user_id', $employee->id)->first();
    expect($signature->verification_code)->not->toBeNull()
        ->and($signature->verification_code_expires_at)->not->toBeNull();

    Mail::assertQueued(DocumentSignatureVerificationCode::class, fn ($mail) => $mail->hasTo('juan@example.com'));
});

test('resending issues a fresh code', function () {
    Mail::fake();

    $employee = signingEmployee();
    $document = pendingContractFor($employee);
    $signature = $document->signatures()->first();
    $signature->update([
        'verification_code' => '111111',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($employee)->post(route('my.documents.send-code', $document));

    expect($signature->refresh()->verification_code)->not->toBe('111111');
    Mail::assertQueued(DocumentSignatureVerificationCode::class);
});

// --- Signing ---

test('a valid code signs the document, records evidence and completes it', function () {
    Mail::fake();

    $employee = signingEmployee();
    $document = pendingContractFor($employee);
    $signature = $document->signatures()->first();
    $signature->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($employee)
        ->post(route('my.documents.sign', $document), ['code' => '654321'])
        ->assertRedirect();

    $signature->refresh();
    expect($signature->status)->toBe(DocumentSignatureStatus::Signed)
        ->and($signature->signed_at)->not->toBeNull()
        ->and($signature->signed_ip)->not->toBeNull()
        ->and($signature->signed_content_hash)->toBe($document->refresh()->contentHash())
        ->and($signature->verification_code)->toBeNull();

    expect($document->status)->toBe(DocumentStatus::Signed)
        ->and($document->signed_at)->not->toBeNull()
        ->and($document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION))->not->toBeNull();

    Mail::assertQueued(DocumentFullySigned::class);
});

test('an invalid code is rejected with a validation error and no state change', function () {
    $employee = signingEmployee();
    $document = pendingContractFor($employee);
    $document->signatures()->first()->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($employee)
        ->from(route('my.documents.show', $document))
        ->post(route('my.documents.sign', $document), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature)
        ->and($document->signatures()->first()->status)->toBe(DocumentSignatureStatus::Pending);
});

test('an expired code cannot sign', function () {
    $employee = signingEmployee();
    $document = pendingContractFor($employee);
    $document->signatures()->first()->update([
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($employee)
        ->from(route('my.documents.show', $document))
        ->post(route('my.documents.sign', $document), ['code' => '654321'])
        ->assertSessionHasErrors('code');

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature);
});

test('the document only completes once every signatory has signed', function () {
    Mail::fake();

    $employee = signingEmployee();
    $legalRep = signingEmployee($employee->organization);

    $document = pendingContractFor($employee);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $legalRep->id,
        'type' => DocumentSignatureType::LegalRep,
        'status' => DocumentSignatureStatus::Pending,
    ]);

    // Employee signs first — document stays pending.
    $employeeSignature = $document->signatures()->where('user_id', $employee->id)->first();
    $employeeSignature->update(['verification_code' => '111111', 'verification_code_expires_at' => now()->addMinutes(15)]);
    $this->actingAs($employee)->post(route('my.documents.sign', $document), ['code' => '111111']);

    expect($document->refresh()->status)->toBe(DocumentStatus::PendingSignature);
    Mail::assertNotQueued(DocumentFullySigned::class);

    // Legal rep signs — now it completes.
    $legalRepSignature = $document->signatures()->where('user_id', $legalRep->id)->first();
    $legalRepSignature->update(['verification_code' => '222222', 'verification_code_expires_at' => now()->addMinutes(15)]);
    $this->actingAs($legalRep)->post(route('my.documents.sign', $document), ['code' => '222222']);

    expect($document->refresh()->status)->toBe(DocumentStatus::Signed);
    Mail::assertQueued(DocumentFullySigned::class);
});

// --- Rejecting ---

test('a rejection marks the document rejected and cancels the remaining signatures', function () {
    $employee = signingEmployee();
    $legalRep = signingEmployee($employee->organization);

    $document = pendingContractFor($employee);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $legalRep->id,
        'type' => DocumentSignatureType::LegalRep,
        'status' => DocumentSignatureStatus::Pending,
    ]);

    $this->actingAs($employee)
        ->post(route('my.documents.reject', $document), ['reason' => 'No estoy de acuerdo.'])
        ->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::Rejected);

    $employeeSignature = $document->signatures()->where('user_id', $employee->id)->first();
    $legalRepSignature = $document->signatures()->where('user_id', $legalRep->id)->first();

    expect($employeeSignature->status)->toBe(DocumentSignatureStatus::Rejected)
        ->and($employeeSignature->rejection_reason)->toBe('No estoy de acuerdo.')
        ->and($legalRepSignature->status)->toBe(DocumentSignatureStatus::Cancelled);
});

// --- Ordered signing ---

test('ordered signing blocks a later signatory until it is their turn', function () {
    Mail::fake();

    $employee = signingEmployee();
    $legalRep = signingEmployee($employee->organization);

    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'ordered_signing' => true,
    ]);
    DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'type' => DocumentSignatureType::Employee,
        'status' => DocumentSignatureStatus::Pending,
        'order' => 1,
    ]);
    $legalRepSignature = DocumentSignature::factory()->create([
        'organization_id' => $employee->organization_id,
        'document_id' => $document->id,
        'user_id' => $legalRep->id,
        'type' => DocumentSignatureType::LegalRep,
        'status' => DocumentSignatureStatus::Pending,
        'order' => 2,
    ]);

    // Legal rep (order 2) cannot get a code before the employee signs.
    expect($document->actionableSignatureFor($legalRep))->toBeNull();

    $this->actingAs($legalRep)->post(route('my.documents.send-code', $document));
    expect($legalRepSignature->refresh()->verification_code)->toBeNull();
});
