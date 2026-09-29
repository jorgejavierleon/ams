<?php

use App\Enums\DocumentType;
use App\Mcp\Tools\DocumentTemplates\CreateDocumentTemplateTool;
use App\Mcp\Tools\DocumentTemplates\DeleteDocumentTemplateTool;
use App\Mcp\Tools\DocumentTemplates\ListDocumentTemplatesTool;
use App\Mcp\Tools\DocumentTemplates\UpdateDocumentTemplateTool;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class)->group('mcp');

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function mcpTemplateAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * Revoke a permission from the shared `admin` role, simulating a tenant that
 * customized it away via the Roles screen.
 */
function revokeTemplateAdminPermission(string $permission): void
{
    Role::findByName('admin')->revokePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

// --- create-document-template ---

test('an admin can create a document template via the create-document-template tool', function () {
    $admin = mcpTemplateAdmin();

    mcpTool($admin, CreateDocumentTemplateTool::class, [
        'title' => 'Vacation notice',
        'type' => DocumentType::Notifications->value,
        'body' => '<p>Estimado {{employee_name}}</p>',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('title', 'Vacation notice')->etc());

    $this->assertDatabaseHas('document_templates', [
        'organization_id' => $admin->organization_id,
        'title' => 'Vacation notice',
    ]);
});

test('an admin without Create:DocumentTemplate is denied by the create-document-template tool', function () {
    $admin = mcpTemplateAdmin();
    revokeTemplateAdminPermission('Create:DocumentTemplate');

    mcpTool($admin, CreateDocumentTemplateTool::class, ['title' => 'Vacation notice'])
        ->assertHasErrors();

    $this->assertDatabaseMissing('document_templates', ['title' => 'Vacation notice']);
});

test('a non-admin is denied by the create-document-template tool', function () {
    $employee = User::factory()->employee()->create();

    mcpTool($employee, CreateDocumentTemplateTool::class, ['title' => 'Vacation notice'])
        ->assertHasErrors();

    $this->assertDatabaseMissing('document_templates', ['title' => 'Vacation notice']);
});

// --- update-document-template ---

test('an admin can update a document template via the update-document-template tool', function () {
    $admin = mcpTemplateAdmin();
    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'title' => 'Old title',
    ]);

    mcpTool($admin, UpdateDocumentTemplateTool::class, [
        'document_template_id' => $template->id,
        'title' => 'New title',
        'type' => DocumentType::Contracts->value,
    ])
        ->assertOk();

    expect($template->refresh())
        ->title->toBe('New title')
        ->type->toBe(DocumentType::Contracts);
});

test('an admin without Update:DocumentTemplate is denied by the update-document-template tool', function () {
    $admin = mcpTemplateAdmin();
    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'title' => 'Old title',
    ]);
    revokeTemplateAdminPermission('Update:DocumentTemplate');

    mcpTool($admin, UpdateDocumentTemplateTool::class, [
        'document_template_id' => $template->id,
        'title' => 'New title',
    ])
        ->assertHasErrors();

    expect($template->refresh()->title)->toBe('Old title');
});

test('a template from another organization is not found by the update-document-template tool', function () {
    $admin = mcpTemplateAdmin();
    $otherAdmin = mcpTemplateAdmin();
    $template = DocumentTemplate::factory()->create(['organization_id' => $otherAdmin->organization_id]);

    mcpTool($admin, UpdateDocumentTemplateTool::class, [
        'document_template_id' => $template->id,
        'title' => 'New title',
    ])
        ->assertHasErrors();
});

// --- delete-document-template ---

test('an admin can delete a document template via the delete-document-template tool', function () {
    $admin = mcpTemplateAdmin();
    $template = DocumentTemplate::factory()->create(['organization_id' => $admin->organization_id]);

    mcpTool($admin, DeleteDocumentTemplateTool::class, ['document_template_id' => $template->id])
        ->assertOk();

    $this->assertSoftDeleted('document_templates', ['id' => $template->id]);
});

test('an admin without Delete:DocumentTemplate is denied by the delete-document-template tool', function () {
    $admin = mcpTemplateAdmin();
    $template = DocumentTemplate::factory()->create(['organization_id' => $admin->organization_id]);
    revokeTemplateAdminPermission('Delete:DocumentTemplate');

    mcpTool($admin, DeleteDocumentTemplateTool::class, ['document_template_id' => $template->id])
        ->assertHasErrors();

    $this->assertDatabaseHas('document_templates', ['id' => $template->id, 'deleted_at' => null]);
});

// --- list-document-templates ---

test('an admin lists only their organization\'s document templates', function () {
    $admin = mcpTemplateAdmin();
    DocumentTemplate::factory()->create(['organization_id' => $admin->organization_id]);
    DocumentTemplate::factory()->create(['organization_id' => $admin->organization_id]);

    $otherAdmin = mcpTemplateAdmin();
    DocumentTemplate::factory()->create(['organization_id' => $otherAdmin->organization_id]);

    mcpTool($admin, ListDocumentTemplatesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('templates', 2)->etc());
});

test('an admin without ViewAny:DocumentTemplate is denied by the list-document-templates tool', function () {
    $admin = mcpTemplateAdmin();
    revokeTemplateAdminPermission('ViewAny:DocumentTemplate');

    mcpTool($admin, ListDocumentTemplatesTool::class)
        ->assertHasErrors();
});
