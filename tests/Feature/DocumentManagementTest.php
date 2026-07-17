<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function documentAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function documentEmployee(User $admin, array $overrides = []): User
{
    return User::factory()->employee()->create(array_merge([
        'organization_id' => $admin->organization_id,
    ], $overrides));
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('documents.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('documents.index'))->assertForbidden();
});

// --- Index ---

test('admin can list documents for their organization', function () {
    $admin = documentAdmin();
    $employee = documentEmployee($admin);

    Document::factory()->count(2)->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
    ]);

    // A document in another organization must not leak into the list.
    $otherAdmin = documentAdmin();
    Document::factory()->create([
        'organization_id' => $otherAdmin->organization_id,
        'user_id' => documentEmployee($otherAdmin)->id,
    ]);

    $this->actingAs($admin)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('documents/index')
                ->has('documents.data', 2)
        );
});

// --- Create ---

test('admin can create a document', function () {
    $admin = documentAdmin();
    $employee = documentEmployee($admin);

    $this->actingAs($admin)
        ->post(route('documents.store'), [
            'title' => 'Contrato de trabajo',
            'type' => DocumentType::Contracts->value,
            'user_id' => $employee->id,
            'body' => '<p>Hola {{employee_name}}</p>',
            'legal_rep_signatories' => '1',
            'ordered_signing' => false,
        ])
        ->assertRedirect(route('documents.index'));

    $this->assertDatabaseHas('documents', [
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'title' => 'Contrato de trabajo',
        'type' => DocumentType::Contracts->value,
        'status' => DocumentStatus::Draft->value,
        'legal_rep_signatories' => 1,
    ]);
});

test('creating a document requires a title and a valid employee', function () {
    $admin = documentAdmin();

    $this->actingAs($admin)
        ->post(route('documents.store'), [
            'title' => '',
            'user_id' => 9999,
            'legal_rep_signatories' => '1',
        ])
        ->assertSessionHasErrors(['title', 'user_id']);
});

// --- Update ---

test('admin can update a document', function () {
    $admin = documentAdmin();
    $employee = documentEmployee($admin);

    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'title' => 'Old title',
    ]);

    $this->actingAs($admin)
        ->patch(route('documents.update', $document), [
            'title' => 'New title',
            'type' => DocumentType::Annexes->value,
            'user_id' => $employee->id,
            'body' => '<p>Updated body</p>',
            'legal_rep_signatories' => '0',
            'ordered_signing' => false,
        ])
        ->assertRedirect(route('documents.index'));

    expect($document->refresh())
        ->title->toBe('New title')
        ->type->toBe(DocumentType::Annexes);
});

// --- Delete ---

test('admin can delete a document', function () {
    $admin = documentAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => documentEmployee($admin)->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect(route('documents.index'));

    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
});

// --- Publish + variable rendering ---

test('publishing a document resolves its body variables and stamps the publish date', function () {
    $admin = documentAdmin();
    $employee = documentEmployee($admin, ['name' => 'Juan Pérez']);

    // A certificate is informational, so publishing it does not spawn
    // signatures — it stays "published" (see DocumentPublishDownloadTest for
    // the signable-document lifecycle).
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'type' => DocumentType::Certificates,
        'body' => '<p>Yo, {{employee_name}}, acepto las condiciones.</p>',
        'status' => DocumentStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.publish', $document))
        ->assertRedirect();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Published)
        ->and($document->published_at)->not->toBeNull()
        ->and($document->body)
        ->toContain('Juan Pérez')
        ->not->toContain('{{employee_name}}');
});

test('a published document cannot be published again', function () {
    $admin = documentAdmin();
    $document = Document::factory()->published()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => documentEmployee($admin)->id,
    ]);

    $this->actingAs($admin)
        ->post(route('documents.publish', $document))
        ->assertForbidden();
});
