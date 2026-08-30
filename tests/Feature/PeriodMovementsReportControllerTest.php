<?php

use App\Enums\LeaveType;
use App\Enums\ShiftType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function periodMovementsIndexUrl(array $query = []): string
{
    return route('payroll-reports.period-movements', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'month',
        ...$query,
    ]);
}

function periodMovementsExportUrl(string $format, array $query = []): string
{
    return route('payroll-reports.period-movements.export', [
        'format' => $format,
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'month',
        ...$query,
    ]);
}

function periodMovementsSpreadsheetFromXlsxResponse(Response $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, TestResponse::fromBaseResponse($response)->streamedContent());

    try {
        return (new XlsxReader)->load($path);
    } finally {
        unlink($path);
    }
}

test('an employee whose contract started in the period appears among the hires', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $newHire = User::factory()->for($organization)->employee()->create([
        'name' => 'Nueva Empleada',
        'contract_start_date' => '2026-08-10',
        'contract_end_date' => null,
    ]);
    $oldHire = User::factory()->for($organization)->employee()->create([
        'contract_start_date' => '2026-01-01',
        'contract_end_date' => null,
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$newHire->id, $oldHire->id],
    ]));

    $movements = $response->viewData('page')['props']['movements'];

    expect(collect($movements['hires'])->pluck('employee'))->toContain('Nueva Empleada')
        ->and(collect($movements['hires'])->pluck('date'))->toContain('10/08/2026')
        ->and($movements['hires'])->toHaveCount(1);
});

test('an employee whose contract ended in the period appears among the terminations', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $leaver = User::factory()->for($organization)->employee()->create([
        'name' => 'Ex Empleado',
        'contract_end_date' => '2026-08-20',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$leaver->id],
    ]));

    $movements = $response->viewData('page')['props']['movements'];

    expect(collect($movements['terminations'])->pluck('employee'))->toContain('Ex Empleado')
        ->and(collect($movements['terminations'])->pluck('date'))->toContain('20/08/2026');
});

test('a licencia that started before the period and ends inside it counts only as a leave end', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Leave::factory()->for($organization)->approved()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Unpaid,
        'start_date' => '2026-07-25',
        'end_date' => '2026-08-05',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $movements = $response->viewData('page')['props']['movements'];

    expect($movements['leaveStarts'])->toBe([])
        ->and($movements['leaveEnds'])->toHaveCount(1)
        ->and($movements['leaveEnds'][0]['endDate'])->toBe('05/08/2026');
});

test('a licencia that starts inside the period and runs past the end counts only as a leave start', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Leave::factory()->for($organization)->approved()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Unpaid,
        'start_date' => '2026-08-28',
        'end_date' => '2026-09-05',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $movements = $response->viewData('page')['props']['movements'];

    expect($movements['leaveEnds'])->toBe([])
        ->and($movements['leaveStarts'])->toHaveCount(1)
        ->and($movements['leaveStarts'][0]['startDate'])->toBe('28/08/2026');
});

test('a pending licencia is not a movement, only approved ones count', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Leave::factory()->for($organization)->pending()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Unpaid,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-12',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $movements = $response->viewData('page')['props']['movements'];

    expect($movements['leaveStarts'])->toBe([])
        ->and($movements['leaveEnds'])->toBe([]);
});

test('an approved vacation overlapping the period appears among vacations regardless of which edge sits outside it', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Leave::factory()->for($organization)->approved()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-07-28',
        'end_date' => '2026-09-03',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $movements = $response->viewData('page')['props']['movements'];

    expect($movements['vacations'])->toHaveCount(1)
        ->and($movements['vacations'][0]['startDate'])->toBe('28/07/2026')
        ->and($movements['vacations'][0]['endDate'])->toBe('03/09/2026');
});

test('a pending vacation request is not counted among approved vacations', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Leave::factory()->for($organization)->pending()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-15',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    expect($response->viewData('page')['props']['movements']['vacations'])->toBe([]);
});

test('shift changes reuse the DT shift-changes report rather than a second notion of a change', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create(['name' => 'Ana']);

    $firstShift = Shift::factory()->for($organization)->create(['type' => ShiftType::Fixed, 'description' => 'de 08:00 a 17:00']);
    $secondShift = Shift::factory()->for($organization)->create(['type' => ShiftType::Rotational, 'description' => 'de 09:00 a 18:00']);

    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $firstShift->id,
        'notification_date' => '2026-07-18',
        'start_date' => '2026-07-20',
        'end_date' => '2026-08-04',
        'requested_by_employee' => false,
    ]);
    ShiftAssignment::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'shift_id' => $secondShift->id,
        'notification_date' => '2026-08-04',
        'start_date' => '2026-08-05',
        'end_date' => null,
        'requested_by_employee' => true,
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $shiftChanges = $response->viewData('page')['props']['movements']['shiftChanges'];

    expect($shiftChanges)->toHaveCount(1)
        ->and($shiftChanges[0]['employee'])->toContain('Ana')
        ->and($shiftChanges[0]['rows'])->toHaveCount(2)
        ->and($shiftChanges[0]['rows'][1]['oldShift'])->toBe('de 08:00 a 17:00')
        ->and($shiftChanges[0]['rows'][1]['newShift'])->toBe('de 09:00 a 18:00');
});

test('selecting no employees produces every movement type empty', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl(['selectAll' => 0]));

    expect($response->viewData('page')['props']['movements'])->toBe([
        'hires' => [],
        'terminations' => [],
        'leaveStarts' => [],
        'leaveEnds' => [],
        'vacations' => [],
        'shiftChanges' => [],
    ]);
});

test('an employee from another organization cannot be resolved into the report', function () {
    $organizationOne = Organization::factory()->create();
    $organizationTwo = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organizationOne->id]);
    $admin->assignRole('admin');
    $otherEmployee = User::factory()->for($organizationTwo)->employee()->create([
        'contract_start_date' => '2026-08-10',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsIndexUrl([
        'selectAll' => 0,
        'ids' => [$otherEmployee->id],
    ]));

    expect($response->viewData('page')['props']['movements']['hires'])->toBe([]);
});

test('the excel export produces one sheet per movement type, in Spanish, even when a type has no movements', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create([
        'contract_start_date' => '2026-08-10',
    ]);

    $response = $this->actingAs($admin)->get(periodMovementsExportUrl('excel', [
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $spreadsheet = periodMovementsSpreadsheetFromXlsxResponse($response->baseResponse);

    expect($spreadsheet->getSheetCount())->toBe(6)
        ->and($spreadsheet->getSheet(0)->getTitle())->toBe('Altas')
        ->and($spreadsheet->getSheet(1)->getTitle())->toBe('Bajas')
        ->and($spreadsheet->getSheet(2)->getTitle())->toBe('Inicio de Licencias')
        ->and($spreadsheet->getSheet(3)->getTitle())->toBe('Fin de Licencias')
        ->and($spreadsheet->getSheet(4)->getTitle())->toBe('Vacaciones Aprobadas')
        ->and($spreadsheet->getSheet(5)->getTitle())->toBe('Cambios de Turno');

    // Bajas has no movements for this selection, but the sheet still exists
    // with its header row rather than being omitted (AC #6).
    $bajasRows = $spreadsheet->getSheet(1)->toArray();
    expect($bajasRows)->not->toBeEmpty();
});

test('a csv or pdf export is rejected: this report is excel only', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(periodMovementsExportUrl('csv'))->assertNotFound();
    $this->actingAs($admin)->get(periodMovementsExportUrl('pdf'))->assertNotFound();
});

test('every export is recorded in the payroll export activity log', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $this->actingAs($admin)->get(periodMovementsExportUrl('excel', [
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]))->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'payroll_export')
        ->where('description', 'Exported payroll report')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['report_type'])->toBe('period-movements')
        ->and($activity->properties['format'])->toBe('excel')
        ->and($activity->properties['employee_ids'])->toContain($employee->id);
});

test('a user without View:PayrollReport is forbidden from the period movements report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('payroll-reports.period-movements'))
        ->assertForbidden();
});

test('a user with View but not Export:PayrollReport cannot download the period movements report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->givePermissionTo('View:PayrollReport');

    $this->actingAs($user)
        ->get(route('payroll-reports.period-movements'))
        ->assertOk();

    $this->actingAs($user)
        ->get(periodMovementsExportUrl('excel'))
        ->assertForbidden();
});
