<?php

use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;

uses()->group('dt');

test('guests cannot access the incidents report', function () {
    $this->get(route('dt.reports.incidents'))
        ->assertRedirect(route('dt.login'));
});

test('dt users without a selected organization are redirected to the selector', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.reports.incidents'))
        ->assertRedirect(route('dt.organization.select'));
});

test('the incidents report renders scoped to the audit session organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    Incident::factory()->for($audited)->create([
        'start_time' => '2026-03-10 08:00:00',
        'description' => 'Audited outage',
    ]);
    Incident::factory()->for($other)->create([
        'start_time' => '2026-03-10 08:00:00',
        'description' => 'Other outage',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.reports.incidents', ['start' => '2026-03-01', 'end' => '2026-03-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/reports/incidents')
            ->where('reportType', 'incidents')
            ->has('report', 1)
            ->where('report.0.description', 'Audited outage')
        );
});

test('the incidents report exposes a computed duration', function () {
    app()->setLocale('es');

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    Incident::factory()->for($organization)->create([
        'start_time' => '2026-03-01 08:00:00',
        'end_time' => '2026-03-01 08:45:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.incidents', ['start' => '2026-03-01', 'end' => '2026-03-31']))
        ->assertInertia(fn ($page) => $page
            ->where('report.0.start_time', '2026-03-01 08:00')
            ->where('report.0.end_time', '2026-03-01 08:45')
            ->where('report.0.duration', '45 minutos')
        );
});

test('an open incident has no end time or duration', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    Incident::factory()->for($organization)->create([
        'start_time' => '2026-03-01 08:00:00',
        'end_time' => null,
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.incidents', ['start' => '2026-03-01', 'end' => '2026-03-31']))
        ->assertInertia(fn ($page) => $page
            ->where('report.0.end_time', null)
            ->where('report.0.duration', null)
        );
});

test('the date range narrows the incidents report to the requested window', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    Incident::factory()->for($organization)->create(['start_time' => '2026-01-10 09:00:00']);
    Incident::factory()->for($organization)->create([
        'start_time' => '2026-02-15 09:00:00',
        'description' => 'February outage',
    ]);
    Incident::factory()->for($organization)->create(['start_time' => '2026-03-20 09:00:00']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.incidents', ['start' => '2026-02-01', 'end' => '2026-02-28']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.start', '2026-02-01')
            ->where('filters.end', '2026-02-28')
            ->has('report', 1)
            ->where('report.0.description', 'February outage')
        );
});

test('the incidents report is empty when no incidents fall in the range', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    Incident::factory()->for($organization)->create(['start_time' => '2026-01-10 09:00:00']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.incidents', ['start' => '2026-02-01', 'end' => '2026-02-28']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('report', 0));
});
