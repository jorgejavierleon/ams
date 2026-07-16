<?php

use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;

uses()->group('dt');

test('guests cannot access the incidents list', function () {
    $this->get(route('dt.incidents.index'))
        ->assertRedirect(route('dt.login'));
});

test('dt users without a selected organization are redirected to the selector', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.incidents.index'))
        ->assertRedirect(route('dt.organization.select'));
});

test('the incidents list renders scoped to the audit session organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    Incident::factory()->for($audited)->create(['description' => 'Audited outage']);
    Incident::factory()->for($other)->create(['description' => 'Other outage']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.incidents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/incidents/index')
            ->has('incidents.data', 1)
            ->where('incidents.data.0.description', 'Audited outage'),
        );
});

test('the incidents list exposes a computed duration', function () {
    app()->setLocale('es');

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    Incident::factory()->for($organization)->create([
        'start_time' => '2026-03-01 08:00:00',
        'end_time' => '2026-03-01 08:45:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.incidents.index'))
        ->assertInertia(fn ($page) => $page
            ->where('incidents.data.0.duration', '45 minutos'),
        );
});

test('the date range filter narrows the incidents to the requested window', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    Incident::factory()->for($organization)->create(['start_time' => '2026-01-10 09:00:00']);
    $inRange = Incident::factory()->for($organization)->create(['start_time' => '2026-02-15 09:00:00']);
    Incident::factory()->for($organization)->create(['start_time' => '2026-03-20 09:00:00']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.incidents.index', ['from' => '2026-02-01', 'to' => '2026-02-28']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.from', '2026-02-01')
            ->where('filters.to', '2026-02-28')
            ->has('incidents.data', 1)
            ->where('incidents.data.0.id', $inRange->id),
        );
});
