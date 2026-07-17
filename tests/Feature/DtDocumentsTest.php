<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;

uses()->group('dt');

test('guests cannot access the documents list', function () {
    $this->get(route('dt.documents.index'))
        ->assertRedirect(route('dt.login'));
});

test('dt users without a selected organization are redirected to the selector', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.documents.index'))
        ->assertRedirect(route('dt.organization.select'));
});

test('the documents list renders scoped to the audit session organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    $employee = User::factory()->employee()->create([
        'organization_id' => $audited->id,
        'name' => 'Juan Pérez',
    ]);

    Document::factory()->for($audited)->create([
        'user_id' => $employee->id,
        'title' => 'Audited contract',
    ]);
    Document::factory()->for($other)->create(['title' => 'Other contract']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/documents/index')
            ->has('documents.data', 1)
            ->where('documents.data.0.title', 'Audited contract')
            ->where('documents.data.0.employee', 'Juan Pérez'),
        );
});

test('the document detail view renders for a document in the audit organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();

    $document = Document::factory()->for($audited)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.documents.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/documents/show')
            ->where('document.id', $document->id),
        );
});

test('documents outside the audit organization are not reachable', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    $document = Document::factory()->for($other)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.documents.show', $document))
        ->assertNotFound();
});

test('a dt inspector can download a document as a pdf', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();

    $document = Document::factory()->for($audited)->create([
        'status' => DocumentStatus::Draft,
    ]);

    $response = $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.documents.download', $document));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

test('a dt inspector cannot download a document outside the audit organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    $document = Document::factory()->for($other)->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.documents.download', $document))
        ->assertNotFound();
});
