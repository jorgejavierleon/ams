<?php

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

uses()->group('dt');

/**
 * Compliance guard for KOL-32.
 *
 * The Resolución 38 art. 27 reports identify the **employer of record** —
 * razón social plus RUT, taken from {@see Company}. When cost centres arrived
 * as the payroll reporting dimension, the tempting shortcut was to render the
 * cost centre in that column instead. That would break the libro de asistencia:
 * a cost centre is an accounting bucket with no RUT and no legal standing.
 *
 * These tests fail if the employer column ever starts resolving through the
 * cost centre.
 */
function dtReportSubject(): array
{
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $company = Company::factory()->create([
        'organization_id' => $organization->id,
        'social_reason' => 'Empleador Legal SpA',
        'rut' => '76111111-6',
    ]);

    $costCenter = CostCenter::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Centro De Costo Operaciones',
    ]);

    $employee = User::factory()->for($organization)->employee()->create([
        'name' => 'Ana',
        'company_id' => $company->id,
        'cost_center_id' => $costCenter->id,
    ]);

    return [$inspector, $organization, $employee, $costCenter];
}

test('the attendance report names the employer by razon social and rut, never the cost centre', function () {
    Mail::fake();

    [$inspector, $organization, $employee, $costCenter] = dtReportSubject();

    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-03 08:00:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-04',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.0.employer', fn (string $value) => str_contains($value, 'Empleador Legal SpA')
                && str_contains($value, '76.111.111-6')
                && ! str_contains($value, $costCenter->name))
        );
});

test('the daily report names the employer by razon social and rut, never the cost centre', function () {
    Mail::fake();

    [$inspector, $organization, $employee, $costCenter] = dtReportSubject();

    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-03 08:00:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.daily', [
            'start' => '2026-03-03',
            'end' => '2026-03-03',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.0.employer', fn (?string $value) => $value !== null
                && str_contains($value, 'Empleador Legal SpA')
                && ! str_contains($value, $costCenter->name))
        );
});

test('an employee with a cost centre but no company reports a null employer rather than the cost centre', function () {
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();
    $costCenter = CostCenter::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Centro De Costo Operaciones',
    ]);
    $employee = User::factory()->for($organization)->employee()->create([
        'name' => 'Ana',
        'company_id' => null,
        'cost_center_id' => $costCenter->id,
    ]);

    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-03 08:00:00',
    ]);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.attendance', [
            'start' => '2026-03-02',
            'end' => '2026-03-04',
            'employees' => [$employee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.0.employer', null));
});
