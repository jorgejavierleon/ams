<?php

use App\Models\Incident;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

uses()->group('dt');

/**
 * Seed one audited employer with a worker who has a mark and a recorded
 * incident, so every report type has at least one row to export.
 *
 * @return array{0: User, 1: Organization}
 */
function seedExportableOrganization(string $name = 'Acme Spa'): array
{
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create(['name' => $name]);
    $employee = User::factory()->for($organization)->employee()->create();

    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-03 08:00:00',
    ]);

    Incident::factory()->for($organization)->create([
        'start_time' => '2026-03-03 08:00:00',
        'end_time' => '2026-03-03 09:00:00',
    ]);

    return [$inspector, $organization];
}

test('guests cannot export a report', function () {
    $this->get(route('dt.reports.export', ['type' => 'attendance', 'format' => 'excel']))
        ->assertRedirect(route('dt.login'));
});

test('dt users without a selected organization are redirected to the selector', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.reports.export', ['type' => 'attendance', 'format' => 'excel']))
        ->assertRedirect(route('dt.organization.select'));
});

test('each report type and format streams a download with the correct content type', function (string $type, string $format, string $mime) {
    [$inspector, $organization] = seedExportableOrganization();

    $response = $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.export', [
            'type' => $type,
            'format' => $format,
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($mime);
})->with([
    'attendance excel' => ['attendance', 'excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'attendance pdf' => ['attendance', 'pdf', 'application/pdf'],
    'attendance word' => ['attendance', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'daily excel' => ['daily', 'excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'daily pdf' => ['daily', 'pdf', 'application/pdf'],
    'daily word' => ['daily', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'shift-changes excel' => ['shift-changes', 'excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'shift-changes pdf' => ['shift-changes', 'pdf', 'application/pdf'],
    'shift-changes word' => ['shift-changes', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'sundays excel' => ['sundays', 'excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'sundays pdf' => ['sundays', 'pdf', 'application/pdf'],
    'sundays word' => ['sundays', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'incidents excel' => ['incidents', 'excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'incidents pdf' => ['incidents', 'pdf', 'application/pdf'],
    'incidents word' => ['incidents', 'word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
]);

test('the download file name includes the report type, date range and organization name', function () {
    [$inspector, $organization] = seedExportableOrganization('Acme Spa');

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.export', [
            'type' => 'attendance',
            'format' => 'excel',
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]))
        ->assertDownload('reporte-de-asistencia_acme-spa_2026-03-01_2026-03-31.xlsx');
});

test('an unknown report type is rejected', function () {
    [$inspector, $organization] = seedExportableOrganization();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.export', ['type' => 'unknown', 'format' => 'excel']))
        ->assertNotFound();
});

test('an unknown export format is rejected', function () {
    [$inspector, $organization] = seedExportableOrganization();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.export', ['type' => 'attendance', 'format' => 'csv']))
        ->assertNotFound();
});

test('the export is scoped to the audit session organization', function () {
    [$inspector, $audited] = seedExportableOrganization('Audited Co');
    $other = Organization::factory()->create(['name' => 'Other Co']);
    $otherEmployee = User::factory()->for($other)->employee()->create();
    Mark::factory()->for($other)->create([
        'user_id' => $otherEmployee->id,
        'date_time' => '2026-03-03 08:00:00',
    ]);

    // The audited organization's file downloads; the cross-organization worker
    // filter is ignored, so no other employer's data can leak in.
    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.reports.export', [
            'type' => 'attendance',
            'format' => 'excel',
            'employees' => [$otherEmployee->id],
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]))
        ->assertOk()
        ->assertDownload('reporte-de-asistencia_audited-co_2026-03-01_2026-03-31.xlsx');
});
