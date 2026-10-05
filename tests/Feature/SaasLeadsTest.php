<?php

use App\Models\Lead;
use App\Models\User;

uses()->group('saas');

function saasLeadsAdmin(): User
{
    return User::factory()->saasUser()->create();
}

// --- Access control ---

test('unauthenticated users are redirected to saas login', function () {
    $this->get(route('saas.leads.index'))->assertRedirect('/saas/login');
});

test('non-saas users are denied access to the leads list', function () {
    $this->actingAs(User::factory()->create(), 'saas')
        ->get(route('saas.leads.index'))
        ->assertForbidden();
});

// --- Index ---

test('the leads list shows submitted leads', function () {
    Lead::create([
        'name' => 'Interesado Prueba',
        'company' => 'Empresa Prueba SpA',
        'email' => 'interesado@empresa.cl',
        'message' => '¿Tienen soporte para turnos rotativos?',
    ]);

    $this->actingAs(saasLeadsAdmin(), 'saas')
        ->get(route('saas.leads.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/leads/index')
                ->has('leads.data', 1)
                ->where('leads.data.0.name', 'Interesado Prueba')
                ->where('leads.data.0.company', 'Empresa Prueba SpA')
                ->where('leads.data.0.email', 'interesado@empresa.cl'),
        );
});

test('the search filter narrows leads by name, company or email', function () {
    Lead::create(['name' => 'Ana Soto', 'company' => 'Acme SpA', 'email' => 'ana@acme.cl']);
    Lead::create(['name' => 'Bruno Diaz', 'company' => 'Otra SpA', 'email' => 'bruno@otra.cl']);

    $this->actingAs(saasLeadsAdmin(), 'saas')
        ->get(route('saas.leads.index', ['search' => 'Acme']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('leads.data', 1)
                ->where('leads.data.0.name', 'Ana Soto'),
        );
});

// --- Destroy ---

test('a saas admin can delete a lead', function () {
    $lead = Lead::create(['name' => 'Ana Soto', 'email' => 'ana@acme.cl']);

    $this->actingAs(saasLeadsAdmin(), 'saas')
        ->delete(route('saas.leads.destroy', $lead))
        ->assertRedirect();

    expect(Lead::find($lead->id))->toBeNull();
});

test('non-saas users cannot delete a lead', function () {
    $lead = Lead::create(['name' => 'Ana Soto', 'email' => 'ana@acme.cl']);

    $this->actingAs(User::factory()->create(), 'saas')
        ->delete(route('saas.leads.destroy', $lead))
        ->assertForbidden();

    expect(Lead::find($lead->id))->not->toBeNull();
});
