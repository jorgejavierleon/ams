<?php

use App\Mail\DtAuditNotification;
use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

uses()->group('dt');

beforeEach(function () {
    Mail::fake();
});

test('the organization selector renders for authenticated dt users', function () {
    $inspector = User::factory()->dtUser()->create();
    Organization::factory()->create(['name' => 'Acme Corp', 'rut' => '12345678-5']);

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.organization.select'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/select-organization')
            ->where('selectedId', null)
            ->where('filters.search', null)
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Acme Corp')
            ->where('organizations.data.0.rut', '12.345.678-5'),
        );
});

test('guests cannot access the organization selector', function () {
    $this->get(route('dt.organization.select'))
        ->assertRedirect(route('dt.login'));
});

test('the employer list is paginated', function () {
    $inspector = User::factory()->dtUser()->create();
    Organization::factory()->count(15)->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.organization.select'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('organizations.data', 10)
            ->where('organizations.total', 15),
        );
});

test('the employer list can be searched by name', function () {
    $inspector = User::factory()->dtUser()->create();
    Organization::factory()->create(['name' => 'Acme Corp']);
    Organization::factory()->create(['name' => 'Globex']);

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.organization.select', ['search' => 'glob']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'glob')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Globex'),
        );
});

test('the employer list can be searched by rut regardless of formatting', function () {
    $inspector = User::factory()->dtUser()->create();
    Organization::factory()->create(['name' => 'Acme Corp', 'rut' => '12345678-5']);
    Organization::factory()->create(['name' => 'Globex', 'rut' => '76543210-9']);

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.organization.select', ['search' => '12.345.678-5']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Acme Corp'),
        );
});

test('selecting an organization stores it in the session and redirects to the dashboard', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.organization.store'), ['organization_id' => $organization->id])
        ->assertRedirect(route('dt.dashboard'))
        ->assertSessionHas('dt_organization_id', $organization->id)
        ->assertSessionHas('organization_name', $organization->name);
});

test('selecting an organization notifies the employer that an audit has begun', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create(['email' => 'employer@example.com']);

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.organization.store'), ['organization_id' => $organization->id]);

    Mail::assertSent(DtAuditNotification::class, fn (DtAuditNotification $mail) => $mail->hasTo('employer@example.com'));
});

test('no audit notice is sent when the employer has no email on record', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create(['email' => null]);

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.organization.store'), ['organization_id' => $organization->id]);

    Mail::assertNothingSent();
});

test('selecting an unknown organization is rejected', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.organization.store'), ['organization_id' => 9999])
        ->assertInvalid(['organization_id'])
        ->assertSessionMissing('dt_organization_id');
});

test('dt users without a selected organization are redirected to the selector', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.dashboard'))
        ->assertRedirect(route('dt.organization.select'));
});

test('the dashboard is accessible once an organization is selected', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.dashboard'))
        ->assertOk();
});

test('the selected organization is shared to the frontend', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create(['name' => 'Globex']);

    $this->actingAs($inspector, 'dt')
        ->withSession([
            'dt_organization_id' => $organization->id,
            'organization_name' => 'Globex',
        ])
        ->get(route('dt.organization.select'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedId', $organization->id)
            ->where('dtOrganization.id', $organization->id)
            ->where('dtOrganization.name', 'Globex'),
        );
});

test('tenant-scoped queries are constrained to the selected audit organization', function () {
    $auditedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $auditedCompany = Company::factory()->for($auditedOrganization)->create();
    Company::factory()->for($otherOrganization)->create();

    session()->put('dt_organization_id', $auditedOrganization->id);

    $companies = Company::all();

    expect($companies)->toHaveCount(1)
        ->and($companies->first()->is($auditedCompany))->toBeTrue();
});
