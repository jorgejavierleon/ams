<?php

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DocumentSignatureRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function signaturesAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

// --- Display ---

test('the document detail page exposes the signatures ordered by signing sequence', function () {
    $admin = signaturesAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Juan Pérez',
    ]);
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'ordered_signing' => true,
    ]);

    $legalRep = DocumentSignature::factory()->for($document)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => User::factory()->create(['organization_id' => $admin->organization_id])->id,
        'type' => DocumentSignatureType::LegalRep,
        'order' => 2,
    ]);
    $employeeSignature = DocumentSignature::factory()->for($document)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'type' => DocumentSignatureType::Employee,
        'status' => DocumentSignatureStatus::Signed,
        'order' => 1,
        'signed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('documents/show')
                ->has('signatures', 2)
                // Employee (order 1) sorts before the legal rep (order 2).
                ->where('signatures.0.id', $employeeSignature->id)
                ->where('signatures.0.name', 'Juan Pérez')
                ->where('signatures.0.order', 1)
                ->where('signatures.0.status.value', DocumentSignatureStatus::Signed->value)
                ->where('signatures.0.can_resend', false)
                ->where('signatures.1.id', $legalRep->id)
                ->where('signatures.1.order', 2)
                ->where('signatures.1.can_resend', true)
        );
});

test('signatures created at publish time are visible on the detail page', function () {
    Notification::fake();

    $admin = signaturesAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'is_legal_rep' => true,
    ]);

    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'type' => DocumentType::Contracts,
        'legal_rep_signatories' => 1,
        'status' => DocumentStatus::Draft,
    ]);

    $this->actingAs($admin)->post(route('documents.publish', $document));

    $this->actingAs($admin)
        ->get(route('documents.show', $document))
        ->assertInertia(fn ($page) => $page->has('signatures', 2));
});

// --- Resend ---

test('resending a pending signature re-sends the signing notification', function () {
    Notification::fake();

    $admin = signaturesAdmin();
    $signatory = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $signatory->id,
    ]);
    $signature = DocumentSignature::factory()->for($document)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $signatory->id,
        'status' => DocumentSignatureStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->post(route('document-signatures.resend', $signature))
        ->assertRedirect();

    Notification::assertSentTo($signatory, DocumentSignatureRequested::class);
});

test('a signature that is not pending cannot be resent', function () {
    Notification::fake();

    $admin = signaturesAdmin();
    $signatory = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $signatory->id,
    ]);
    $signature = DocumentSignature::factory()->for($document)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $signatory->id,
        'status' => DocumentSignatureStatus::Signed,
        'signed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('document-signatures.resend', $signature))
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('non-admins cannot resend a signature', function () {
    $admin = signaturesAdmin();
    $signatory = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $signatory->id,
    ]);
    $signature = DocumentSignature::factory()->for($document)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $signatory->id,
    ]);

    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->post(route('document-signatures.resend', $signature))
        ->assertForbidden();
});
