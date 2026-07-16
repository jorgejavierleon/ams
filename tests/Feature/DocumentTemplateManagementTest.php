<?php

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\DocumentVar;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function templateAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('document-templates.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('document-templates.index'))->assertForbidden();
});

// --- Index ---

test('admin can list templates for their organization with a variable count', function () {
    $admin = templateAdmin();
    $variable = DocumentVar::factory()->create(['key' => '{{employee_name}}']);

    DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'title' => 'Standard contract',
        'body' => '<p>Hola '.$variable->key.'</p>',
    ]);

    // A template in another organization must not leak into the list.
    $otherAdmin = templateAdmin();
    DocumentTemplate::factory()->create(['organization_id' => $otherAdmin->organization_id]);

    $this->actingAs($admin)
        ->get(route('document-templates.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('document-templates/index')
                ->has('templates.data', 1)
                ->where('templates.data.0.variable_count', 1)
        );
});

// --- Create ---

test('admin can create a template', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->post(route('document-templates.store'), [
            'title' => 'Vacation notice',
            'type' => DocumentType::Notifications->value,
            'body' => '<p>Estimado {{employee_name}}</p>',
        ])
        ->assertRedirect(route('document-templates.index'));

    $this->assertDatabaseHas('document_templates', [
        'organization_id' => $admin->organization_id,
        'title' => 'Vacation notice',
        'type' => DocumentType::Notifications->value,
    ]);
});

test('creating a template requires a title', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->post(route('document-templates.store'), ['title' => ''])
        ->assertSessionHasErrors(['title']);
});

// --- Update ---

test('admin can update a template', function () {
    $admin = templateAdmin();
    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'title' => 'Old title',
    ]);

    $this->actingAs($admin)
        ->patch(route('document-templates.update', $template), [
            'title' => 'New title',
            'type' => DocumentType::Contracts->value,
            'body' => '<p>Updated</p>',
        ])
        ->assertRedirect(route('document-templates.index'));

    expect($template->refresh())
        ->title->toBe('New title')
        ->type->toBe(DocumentType::Contracts);
});

// --- Load template into a document body ---

test('the body endpoint returns the template body for the load-template action', function () {
    $admin = templateAdmin();
    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'body' => '<p>Contrato para {{employee_name}}</p>',
    ]);

    $this->actingAs($admin)
        ->getJson(route('document-templates.body', $template))
        ->assertOk()
        ->assertExactJson(['body' => '<p>Contrato para {{employee_name}}</p>']);
});

test('the create document form exposes the available templates', function () {
    $admin = templateAdmin();
    DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'title' => 'Loadable template',
    ]);

    $this->actingAs($admin)
        ->get(route('documents.create'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('templates', 1)
                ->where('templates.0.title', 'Loadable template')
        );
});

// --- Soft delete + restore ---

test('admin can soft-delete and restore a template', function () {
    $admin = templateAdmin();
    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->actingAs($admin)
        ->delete(route('document-templates.destroy', $template))
        ->assertRedirect(route('document-templates.index'));

    $this->assertSoftDeleted('document_templates', ['id' => $template->id]);

    $this->actingAs($admin)
        ->patch(route('document-templates.restore', $template->id))
        ->assertRedirect(route('document-templates.index'));

    expect($template->fresh()->trashed())->toBeFalse();
});

test('a template cannot be restored from another organization', function () {
    $admin = templateAdmin();
    $otherAdmin = templateAdmin();

    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $template->delete();

    $this->actingAs($otherAdmin)
        ->patch(route('document-templates.restore', $template->id))
        ->assertNotFound();
});
