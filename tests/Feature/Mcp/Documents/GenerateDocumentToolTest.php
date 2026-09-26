<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Mcp\Servers\KolviServer;
use App\Mcp\Tools\Documents\GenerateDocumentTool;
use App\Models\Document;
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

function mcpDocumentAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

test('an admin generates a draft document from a template for an employee', function () {
    $admin = mcpDocumentAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'first_name' => 'Ana',
        'last_name' => 'Soto',
    ]);
    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'title' => 'Standard contract',
        'type' => DocumentType::Contracts,
        'body' => '<p>Estimado {{employee_name}}</p>',
    ]);

    KolviServer::actingAs($admin)
        ->tool(GenerateDocumentTool::class, [
            'document_template_id' => $template->id,
            'user_id' => $employee->id,
        ])
        ->assertOk()
        ->assertStructuredContent(
            fn ($json) => $json
                ->where('title', 'Standard contract')
                ->where('status', 'draft')
                ->etc()
        );

    $document = Document::first();

    expect($document)->not->toBeNull()
        ->and($document->user_id)->toBe($employee->id)
        ->and($document->type)->toBe(DocumentType::Contracts)
        ->and($document->status)->toBe(DocumentStatus::Draft)
        // The stored draft body keeps the raw placeholder, exactly like the
        // web "Load Template" + Save flow -- resolution only happens at publish.
        ->and($document->body)->toBe('<p>Estimado {{employee_name}}</p>')
        ->and($document->published_at)->toBeNull();
});

test('the generate-document tool response includes the resolved preview without altering the stored draft', function () {
    $admin = mcpDocumentAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'first_name' => 'Ana',
        'last_name' => 'Soto',
    ]);
    $template = DocumentTemplate::factory()->create([
        'organization_id' => $admin->organization_id,
        'body' => '<p>Estimado {{employee_first_name}}</p>',
    ]);

    KolviServer::actingAs($admin)
        ->tool(GenerateDocumentTool::class, [
            'document_template_id' => $template->id,
            'user_id' => $employee->id,
        ])
        ->assertOk()
        ->assertStructuredContent(
            fn ($json) => $json
                ->where('resolved_body_preview', '<p>Estimado Ana</p>')
                ->etc()
        );
});

test('an admin without Create:Document is denied by the generate-document tool', function () {
    $admin = mcpDocumentAdmin();
    Role::findByName('admin')->revokePermissionTo('Create:Document');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $template = DocumentTemplate::factory()->create(['organization_id' => $admin->organization_id]);

    KolviServer::actingAs($admin)
        ->tool(GenerateDocumentTool::class, [
            'document_template_id' => $template->id,
            'user_id' => $employee->id,
        ])
        ->assertHasErrors();

    expect(Document::count())->toBe(0);
});

test('a non-admin is denied by the generate-document tool', function () {
    $employee = User::factory()->employee()->create();

    KolviServer::actingAs($employee)
        ->tool(GenerateDocumentTool::class, [
            'document_template_id' => 1,
            'user_id' => $employee->id,
        ])
        ->assertHasErrors();

    expect(Document::count())->toBe(0);
});

test('generating a document never publishes it', function () {
    $admin = mcpDocumentAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $template = DocumentTemplate::factory()->create(['organization_id' => $admin->organization_id]);

    KolviServer::actingAs($admin)
        ->tool(GenerateDocumentTool::class, [
            'document_template_id' => $template->id,
            'user_id' => $employee->id,
        ])
        ->assertOk();

    $document = Document::first();

    expect($document->status)->toBe(DocumentStatus::Draft)
        ->and($document->published_at)->toBeNull()
        ->and($document->signatures)->toBeEmpty();
});

test('a template from another organization is not found by the generate-document tool', function () {
    $admin = mcpDocumentAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $otherAdmin = mcpDocumentAdmin();
    $template = DocumentTemplate::factory()->create(['organization_id' => $otherAdmin->organization_id]);

    KolviServer::actingAs($admin)
        ->tool(GenerateDocumentTool::class, [
            'document_template_id' => $template->id,
            'user_id' => $employee->id,
        ])
        ->assertHasErrors();

    expect(Document::count())->toBe(0);
});

test('an employee from another organization is refused by the generate-document tool', function () {
    $admin = mcpDocumentAdmin();
    $template = DocumentTemplate::factory()->create(['organization_id' => $admin->organization_id]);

    $otherAdmin = mcpDocumentAdmin();
    $otherEmployee = User::factory()->employee()->create(['organization_id' => $otherAdmin->organization_id]);

    KolviServer::actingAs($admin)
        ->tool(GenerateDocumentTool::class, [
            'document_template_id' => $template->id,
            'user_id' => $otherEmployee->id,
        ])
        ->assertHasErrors();

    expect(Document::count())->toBe(0);
});
