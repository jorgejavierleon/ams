<?php

use App\Enums\WorkdayStatus;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * A worked day: a mark (so the attendance/absence resolution counts it as
 * attended) plus a matching Workday row (so the hour totals sum something),
 * mirroring PayrollPeriodSummaryServiceTest's fixture.
 */
function summaryReportWorkedDay(Organization $organization, User $employee, string $date, string $workedTime = '08:00:00'): Workday
{
    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => "{$date} 08:00:00",
    ]);

    return Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => $workedTime,
        'missing_time' => '00:00:00',
        'in_time_difference' => '00:00:00',
        'status' => WorkdayStatus::Regular,
    ]);
}

function summaryReportStreamedContent(Response $response): string
{
    return TestResponse::fromBaseResponse($response)->streamedContent();
}

function summarySpreadsheetFromXlsxResponse(Response $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, summaryReportStreamedContent($response));

    try {
        return (new XlsxReader)->load($path);
    } finally {
        unlink($path);
    }
}

function summaryExportUrl(string $format, array $query = []): string
{
    return route('payroll-reports.summary.export', ['format' => $format, ...$query]);
}

test('an admin can view the summary report with one row per employee and a consolidated total', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employeeOne = User::factory()->for($organization)->employee()->create();
    $employeeTwo = User::factory()->for($organization)->employee()->create();

    summaryReportWorkedDay($organization, $employeeOne, '2026-08-03', '08:00:00');
    summaryReportWorkedDay($organization, $employeeTwo, '2026-08-03', '06:00:00');

    $this->actingAs($admin)
        ->get(route('payroll-reports.summary', [
            'period_year' => 2026,
            'period_month' => 8,
            'period_type' => 'first_fortnight',
            'selectAll' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payroll-reports/summary')
            ->has('rows', 2)
            ->where('total.employeeCount', 2)
            ->where('total.workedHours', '14:00:00')
        );
});

test('the consolidated total equals the sum of the rows', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employeeOne = User::factory()->for($organization)->employee()->create();
    $employeeTwo = User::factory()->for($organization)->employee()->create();

    summaryReportWorkedDay($organization, $employeeOne, '2026-08-03', '05:00:00');
    summaryReportWorkedDay($organization, $employeeTwo, '2026-08-03', '07:30:00');

    $response = $this->actingAs($admin)->get(route('payroll-reports.summary', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 1,
    ]));

    $rows = $response->viewData('page')['props']['rows'];
    $total = $response->viewData('page')['props']['total'];

    $sumSeconds = collect($rows)
        ->sum(fn (array $row): int => Carbon::createFromFormat('H:i:s', $row['workedHours'])->diffInSeconds(Carbon::createFromFormat('H:i:s', '00:00:00')));

    expect($sumSeconds)->toBe((int) Carbon::createFromFormat('H:i:s', $total['workedHours'])->diffInSeconds(Carbon::createFromFormat('H:i:s', '00:00:00')));
});

test('an employee excluded from the selection does not appear in the report', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $included = User::factory()->for($organization)->employee()->create();
    $excluded = User::factory()->for($organization)->employee()->create();

    summaryReportWorkedDay($organization, $included, '2026-08-03');
    summaryReportWorkedDay($organization, $excluded, '2026-08-03');

    $response = $this->actingAs($admin)->get(route('payroll-reports.summary', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 1,
        'ids' => [$excluded->id],
    ]));

    $rows = $response->viewData('page')['props']['rows'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['userId'])->toBe($included->id);
});

test('each export format streams a download with the correct content type', function (string $format, string $mime) {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();
    summaryReportWorkedDay($organization, $employee, '2026-08-03');

    $response = $this->actingAs($admin)->get(summaryExportUrl($format, [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 1,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($mime);
})->with([
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'csv' => ['csv', 'text/csv'],
    'pdf' => ['pdf', 'application/pdf'],
]);

test('the excel export contains the same worked hours figure shown on screen', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();
    summaryReportWorkedDay($organization, $employee, '2026-08-03', '07:15:00');

    $query = [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 1,
    ];

    $screen = $this->actingAs($admin)->get(route('payroll-reports.summary', $query));
    $onScreenWorkedHours = $screen->viewData('page')['props']['rows'][0]['workedHours'];

    $excelResponse = $this->actingAs($admin)->get(summaryExportUrl('excel', $query));
    $sheet = summarySpreadsheetFromXlsxResponse($excelResponse->baseResponse)->getActiveSheet();

    expect($sheet->getCell('C4')->getValue())->toBe($onScreenWorkedHours)
        ->and($onScreenWorkedHours)->toBe('07:15:00');
});

test('an unknown export format is rejected', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('payroll-reports.summary.export', ['format' => 'word']))
        ->assertNotFound();
});

test('an export with unresolved findings is rejected without explicit confirmation', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $workday = summaryReportWorkedDay($organization, $employee, '2026-08-03');
    MarkModification::factory()->for($organization)->create([
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);

    $response = $this->actingAs($admin)->get(summaryExportUrl('excel', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 1,
    ]));

    $response->assertStatus(422);
});

test('an export with unresolved findings proceeds once explicitly confirmed, and the confirmation is recorded', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $workday = summaryReportWorkedDay($organization, $employee, '2026-08-03');
    MarkModification::factory()->for($organization)->create([
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);

    $response = $this->actingAs($admin)->get(summaryExportUrl('excel', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 1,
        'confirmed' => 1,
    ]));

    $response->assertOk();

    expect(Activity::query()->where('log_name', 'payroll_export')->where('description', 'Confirmed payroll export despite unresolved attendance data')->exists())->toBeTrue();
});

test('every export is recorded in the payroll export activity log', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();
    summaryReportWorkedDay($organization, $employee, '2026-08-03');

    $this->actingAs($admin)->get(summaryExportUrl('pdf', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 1,
    ]))->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'payroll_export')
        ->where('description', 'Exported payroll report')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['report_type'])->toBe('payroll-summary')
        ->and($activity->properties['format'])->toBe('pdf')
        ->and($activity->properties['employee_ids'])->toContain($employee->id);
});

test('a user without View:PayrollReport is forbidden from the summary report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('payroll-reports.summary'))
        ->assertForbidden();
});

test('a user with View but not Export:PayrollReport cannot download the report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->givePermissionTo('View:PayrollReport');

    $this->actingAs($user)
        ->get(route('payroll-reports.summary'))
        ->assertOk();

    $this->actingAs($user)
        ->get(summaryExportUrl('excel'))
        ->assertForbidden();
});

test('the summary report is scoped to the current organization', function () {
    $organizationOne = Organization::factory()->create();
    $organizationTwo = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organizationOne->id]);
    $admin->assignRole('admin');
    $ownEmployee = User::factory()->for($organizationOne)->employee()->create();
    $otherEmployee = User::factory()->for($organizationTwo)->employee()->create();

    summaryReportWorkedDay($organizationOne, $ownEmployee, '2026-08-03');
    summaryReportWorkedDay($organizationTwo, $otherEmployee, '2026-08-03');

    $response = $this->actingAs($admin)->get(route('payroll-reports.summary', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'first_fortnight',
        'selectAll' => 0,
        'ids' => [$ownEmployee->id, $otherEmployee->id],
    ]));

    $rows = $response->viewData('page')['props']['rows'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['userId'])->toBe($ownEmployee->id);
});
