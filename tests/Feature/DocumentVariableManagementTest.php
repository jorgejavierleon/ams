<?php

use App\Models\DocumentVar;
use App\Models\User;

uses()->group('saas');

function documentVarSaasAdmin(): User
{
    return User::factory()->saasUser()->create();
}

// --- Access control ---

test('unauthenticated users are redirected to saas login', function () {
    $this->get(route('saas.document-variables.index'))->assertRedirect('/saas/login');
});

test('non-saas users are denied access', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'saas')
        ->get(route('saas.document-variables.index'))
        ->assertForbidden();
});

// --- Index ---

test('saas admin can list document variables', function () {
    DocumentVar::factory()->count(3)->create();

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->get(route('saas.document-variables.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/document-variables/index')
                ->has('variables.data', 3)
        );
});

test('document variables index can be searched by key', function () {
    DocumentVar::factory()->create(['name' => 'Employee name', 'key' => '{{employee_name}}']);
    DocumentVar::factory()->create(['name' => 'Company rut', 'key' => '{{company_rut}}']);

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->get(route('saas.document-variables.index', ['search' => 'employee']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('variables.data', 1)
                ->where('variables.data.0.key', '{{employee_name}}')
        );
});

test('document variables index defaults to sorting by name ascending and echoes filters', function () {
    DocumentVar::factory()->create(['name' => 'Zeta', 'key' => '{{zeta}}']);
    DocumentVar::factory()->create(['name' => 'Alpha', 'key' => '{{alpha}}']);

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->get(route('saas.document-variables.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('variables.data.0.name', 'Alpha')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
        );
});

test('document variables index ignores a disallowed sort column', function () {
    DocumentVar::factory()->create(['name' => 'Beta', 'key' => '{{beta}}']);
    DocumentVar::factory()->create(['name' => 'Alpha', 'key' => '{{alpha}}']);

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->get(route('saas.document-variables.index', ['sort' => 'description', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('variables.data.0.name', 'Alpha')
        );
});

// --- Create ---

test('saas admin can view the create page', function () {
    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->get(route('saas.document-variables.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('saas/document-variables/create'));
});

test('saas admin can create a document variable', function () {
    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->post(route('saas.document-variables.store'), [
            'name' => 'Employee name',
            'key' => '{{employee_name}}',
            'description' => 'The full name of the employee.',
        ])
        ->assertRedirect(route('saas.document-variables.index'));

    $this->assertDatabaseHas('document_vars', [
        'name' => 'Employee name',
        'key' => '{{employee_name}}',
        'description' => 'The full name of the employee.',
    ]);
});

test('a document variable can be created without a description', function () {
    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->post(route('saas.document-variables.store'), [
            'name' => 'Company rut',
            'key' => '{{company_rut}}',
        ])
        ->assertRedirect(route('saas.document-variables.index'));

    $this->assertDatabaseHas('document_vars', ['key' => '{{company_rut}}', 'description' => null]);
});

test('creating a document variable requires a unique key', function () {
    DocumentVar::factory()->create(['key' => '{{employee_name}}']);

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->post(route('saas.document-variables.store'), [
            'name' => 'Employee name',
            'key' => '{{employee_name}}',
        ])
        ->assertSessionHasErrors('key');
});

test('creating a document variable validates required fields', function () {
    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->post(route('saas.document-variables.store'), [])
        ->assertSessionHasErrors(['name', 'key']);
});

test('creating a document variable rejects a key that is not snake_case braces', function (string $key) {
    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->post(route('saas.document-variables.store'), [
            'name' => 'Invalid',
            'key' => $key,
        ])
        ->assertSessionHasErrors('key');
})->with([
    'no braces' => ['employee_name'],
    'single braces' => ['{employee_name}'],
    'camelCase' => ['{{employeeName}}'],
    'kebab-case' => ['{{employee-name}}'],
    'leading digit' => ['{{1employee}}'],
    'spaces inside' => ['{{employee name}}'],
    'uppercase' => ['{{EMPLOYEE}}'],
]);

// --- Edit / Update ---

test('saas admin can view the edit page', function () {
    $variable = DocumentVar::factory()->create();

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->get(route('saas.document-variables.edit', $variable))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/document-variables/edit')
                ->where('variable.id', $variable->id)
        );
});

test('saas admin can update a document variable', function () {
    $variable = DocumentVar::factory()->create(['name' => 'Old name', 'key' => '{{old_key}}']);

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->patch(route('saas.document-variables.update', $variable), [
            'name' => 'New name',
            'key' => '{{new_key}}',
            'description' => 'Updated.',
        ])
        ->assertRedirect(route('saas.document-variables.index'));

    expect($variable->fresh())
        ->name->toBe('New name')
        ->key->toBe('{{new_key}}');
});

test('updating a document variable keeps its own key valid', function () {
    $variable = DocumentVar::factory()->create(['key' => '{{employee_name}}']);

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->patch(route('saas.document-variables.update', $variable), [
            'name' => 'Renamed',
            'key' => '{{employee_name}}',
        ])
        ->assertRedirect(route('saas.document-variables.index'));

    $this->assertDatabaseHas('document_vars', ['id' => $variable->id, 'name' => 'Renamed']);
});

test('updating a document variable rejects a key already used by another', function () {
    DocumentVar::factory()->create(['key' => '{{company_rut}}']);
    $variable = DocumentVar::factory()->create(['key' => '{{employee_name}}']);

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->patch(route('saas.document-variables.update', $variable), [
            'name' => 'Clash',
            'key' => '{{company_rut}}',
        ])
        ->assertSessionHasErrors('key');
});

// --- Delete ---

test('saas admin can delete a document variable', function () {
    $variable = DocumentVar::factory()->create();

    $this->actingAs(documentVarSaasAdmin(), 'saas')
        ->delete(route('saas.document-variables.destroy', $variable))
        ->assertRedirect(route('saas.document-variables.index'));

    $this->assertDatabaseMissing('document_vars', ['id' => $variable->id]);
});
