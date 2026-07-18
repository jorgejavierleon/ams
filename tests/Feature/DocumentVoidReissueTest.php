<?php

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

/**
 * An admin bound to a real organization so documents scope correctly.
 */
function voidingAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * An employee in the admin's organization to own the documents under test.
 */
function voidingEmployeeFor(User $admin): User
{
    return User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
}

// --- Void ---

test('voiding a document cancels its pending signatures and blocks further signing', function () {
    $admin = voidingAdmin();
    $employee = voidingEmployeeFor($admin);

    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
    ]);
    $signature = DocumentSignature::factory()->create([
        'organization_id' => $admin->organization_id,
        'document_id' => $document->id,
        'user_id' => $employee->id,
        'type' => DocumentSignatureType::Employee,
        'status' => DocumentSignatureStatus::Pending,
        'verification_code' => '123456',
        'verification_code_expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($admin)
        ->post(route('documents.void', $document))
        ->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::Voided);

    $signature->refresh();
    expect($signature->status)->toBe(DocumentSignatureStatus::Cancelled)
        ->and($signature->verification_code)->toBeNull()
        ->and($signature->verification_code_expires_at)->toBeNull();

    // The employee can no longer act on the cancelled signature.
    expect($document->actionableSignatureFor($employee))->toBeNull();
});

test('voiding records an activity-log entry', function () {
    $admin = voidingAdmin();
    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => voidingEmployeeFor($admin)->id,
    ]);

    $this->actingAs($admin)->post(route('documents.void', $document));

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => Document::class,
        'subject_id' => $document->id,
        'event' => 'voided',
        'causer_id' => $admin->id,
    ]);
});

test('a published informational document can be voided', function () {
    $admin = voidingAdmin();
    $document = Document::factory()->published()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => voidingEmployeeFor($admin)->id,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.void', $document))
        ->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::Voided);
});

test('a draft, signed or rejected document cannot be voided', function (DocumentStatus $status) {
    $admin = voidingAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => voidingEmployeeFor($admin)->id,
        'status' => $status,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.void', $document))
        ->assertForbidden();

    expect($document->refresh()->status)->toBe($status);
})->with([
    'draft' => [DocumentStatus::Draft],
    'signed' => [DocumentStatus::Signed],
    'rejected' => [DocumentStatus::Rejected],
]);

// --- Duplicate as draft ---

test('duplicating a voided document produces an editable draft with copied fields and no signatures', function () {
    $admin = voidingAdmin();
    $employee = voidingEmployeeFor($admin);

    $original = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'title' => 'Contrato de trabajo',
        'body' => '<p>Cuerpo congelado.</p>',
        'legal_rep_signatories' => 2,
        'ordered_signing' => true,
        'status' => DocumentStatus::Voided,
        'published_at' => now(),
        'signed_at' => now(),
    ]);
    DocumentSignature::factory()->create([
        'organization_id' => $admin->organization_id,
        'document_id' => $original->id,
        'user_id' => $employee->id,
        'type' => DocumentSignatureType::Employee,
        'status' => DocumentSignatureStatus::Cancelled,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.duplicate', $original))
        ->assertRedirect();

    $copy = Document::query()->where('id', '!=', $original->id)->latest('id')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->title)->toBe('Contrato de trabajo (copia)')
        ->and($copy->type)->toBe($original->type)
        ->and($copy->user_id)->toBe($employee->id)
        ->and($copy->body)->toBe('<p>Cuerpo congelado.</p>')
        ->and($copy->legal_rep_signatories)->toBe(2)
        ->and($copy->ordered_signing)->toBeTrue()
        ->and($copy->status)->toBe(DocumentStatus::Draft)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->signed_at)->toBeNull()
        ->and($copy->signatures()->count())->toBe(0);
});

test('duplicate lands the admin on the new draft edit form', function () {
    $admin = voidingAdmin();
    $original = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => voidingEmployeeFor($admin)->id,
        'status' => DocumentStatus::Signed,
    ]);

    $response = $this->actingAs($admin)->post(route('documents.duplicate', $original));

    $copy = Document::query()->where('id', '!=', $original->id)->latest('id')->firstOrFail();

    $response->assertRedirect(route('documents.edit', $copy));
});

test('a draft, published or pending document cannot be duplicated', function (DocumentStatus $status) {
    $admin = voidingAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => voidingEmployeeFor($admin)->id,
        'status' => $status,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.duplicate', $document))
        ->assertForbidden();

    expect(Document::query()->count())->toBe(1);
})->with([
    'draft' => [DocumentStatus::Draft],
    'published' => [DocumentStatus::Published],
    'pending_signature' => [DocumentStatus::PendingSignature],
]);

// --- Access control ---

test('a non-admin cannot void or duplicate a document', function () {
    $admin = voidingAdmin();
    $employee = voidingEmployeeFor($admin);
    $employee->assignRole('employee');

    $document = Document::factory()->pendingSignature()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($employee)
        ->post(route('documents.void', $document))
        ->assertForbidden();

    $this->actingAs($employee)
        ->post(route('documents.duplicate', $document))
        ->assertForbidden();
});
