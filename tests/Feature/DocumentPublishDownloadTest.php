<?php

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DocumentSignatureRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function publishAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function publishEmployee(User $admin, array $overrides = []): User
{
    return User::factory()->employee()->create(array_merge([
        'organization_id' => $admin->organization_id,
    ], $overrides));
}

// --- Access control ---

test('unauthenticated users cannot download a document', function () {
    $admin = publishAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => publishEmployee($admin)->id,
    ]);

    $this->get(route('documents.download', $document))->assertRedirect(route('login'));
});

test('non-admin users cannot download a document', function () {
    $admin = publishAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => publishEmployee($admin)->id,
    ]);

    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('documents.download', $document))->assertForbidden();
});

// --- Publish lifecycle ---

test('publishing a signable document creates signatures, notifies signatories and moves it to pending signature', function () {
    Notification::fake();

    $admin = publishAdmin();
    $employee = publishEmployee($admin, ['name' => 'Juan Pérez']);
    $legalRep = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'is_legal_rep' => true,
    ]);

    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'type' => DocumentType::Contracts,
        'legal_rep_signatories' => 1,
        'ordered_signing' => false,
        'body' => '<p>Contrato de {{employee_name}}.</p>',
        'status' => DocumentStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.publish', $document))
        ->assertRedirect();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::PendingSignature)
        ->and($document->published_at)->not->toBeNull()
        ->and($document->body)->toContain('Juan Pérez')
        ->not->toContain('{{employee_name}}');

    expect($document->signatures()->count())->toBe(2);

    $employeeSignature = $document->signatures()->where('user_id', $employee->id)->first();
    expect($employeeSignature->type)->toBe(DocumentSignatureType::Employee)
        ->and($employeeSignature->status)->toBe(DocumentSignatureStatus::Pending);

    $legalRepSignature = $document->signatures()->where('user_id', $legalRep->id)->first();
    expect($legalRepSignature->type)->toBe(DocumentSignatureType::LegalRep);

    Notification::assertSentTo($employee, DocumentSignatureRequested::class);
    Notification::assertSentTo($legalRep, DocumentSignatureRequested::class);
});

test('ordered signing numbers the signatories employee-first', function () {
    Notification::fake();

    $admin = publishAdmin();
    $employee = publishEmployee($admin);
    $legalRep = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'is_legal_rep' => true,
    ]);

    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'type' => DocumentType::Contracts,
        'legal_rep_signatories' => 1,
        'ordered_signing' => true,
        'status' => DocumentStatus::Draft,
    ]);

    $this->actingAs($admin)->post(route('documents.publish', $document));

    expect($document->signatures()->where('user_id', $employee->id)->value('order'))->toBe(1)
        ->and($document->signatures()->where('user_id', $legalRep->id)->value('order'))->toBe(2);
});

test('publishing a non-signable document stays published without signatures', function () {
    Notification::fake();

    $admin = publishAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => publishEmployee($admin)->id,
        'type' => DocumentType::Certificates,
        'status' => DocumentStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.publish', $document))
        ->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::Published)
        ->and($document->signatures()->count())->toBe(0);

    Notification::assertNothingSent();
});

// --- Download ---

test('a draft document can be downloaded as a pdf', function () {
    $admin = publishAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => publishEmployee($admin)->id,
        'status' => DocumentStatus::Draft,
    ]);

    $response = $this->actingAs($admin)->get(route('documents.download', $document));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

test('a published document can be downloaded as a pdf', function () {
    $admin = publishAdmin();
    $document = Document::factory()->published()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => publishEmployee($admin)->id,
    ]);

    $this->actingAs($admin)
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a signed document downloads the stored signed pdf, not a fresh render', function () {
    Storage::fake('public');

    $admin = publishAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => publishEmployee($admin)->id,
        'status' => DocumentStatus::Signed,
    ]);

    $document->addMediaFromString('SIGNED-PDF-EVIDENCE-BYTES')
        ->usingFileName('signed.pdf')
        ->toMediaCollection(Document::SIGNED_MEDIA_COLLECTION);

    $media = $document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION);

    $response = $this->actingAs($admin)->get(route('documents.download', $document));

    $response->assertOk();
    // The download streams the stored signed artifact itself rather than
    // re-rendering the body, so the signature evidence block is included.
    expect($response->baseResponse->getFile()->getPathname())->toBe($media->getPath());
});
