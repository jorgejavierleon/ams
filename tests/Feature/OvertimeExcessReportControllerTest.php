<?php

use App\Enums\OvertimeCalculationState;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use App\Services\LegalHourLimitVersions;
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

function overtimeExcessIndexUrl(array $query = []): string
{
    return route('payroll-reports.overtime-excess', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'month',
        ...$query,
    ]);
}

function overtimeExcessExportUrl(string $format, array $query = []): string
{
    return route('payroll-reports.overtime-excess.export', [
        'format' => $format,
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'month',
        ...$query,
    ]);
}

function overtimeExcessStreamedContent(Response $response): string
{
    return TestResponse::fromBaseResponse($response)->streamedContent();
}

function overtimeExcessSpreadsheetFromXlsxResponse(Response $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, overtimeExcessStreamedContent($response));

    try {
        return (new XlsxReader)->load($path);
    } finally {
        unlink($path);
    }
}

/**
 * A computed day carrying the given calculated overtime, mirroring the
 * helper in OvertimePayBucketClassifierTest so both suites stay consistent.
 *
 * @return array{0: Workday, 1: User, 2: Organization}
 */
function excessDay(string $date, string $calculatedOvertime, ?Organization $organization = null, ?User $employee = null): array
{
    $organization ??= Organization::factory()->create();
    $employee ??= User::factory()->for($organization)->employee()->create();
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'worked_time' => $calculatedOvertime,
        'post_shift_excess' => $calculatedOvertime,
        'calculated_overtime' => $calculatedOvertime,
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($calculatedOvertime),
        'overtime_calculated_at' => now(),
    ]);

    return [$workday, $supervisor, $organization];
}

function excessWeek(array $props): array
{
    return collect($props['weeks'])->firstWhere('start', $props['week']);
}

test('an authorised weekday lands in its pay bucket with no unauthorised remainder', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    [$workday, $supervisor] = excessDay('2026-08-04', '02:00:00', $organization); // Tuesday
    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$workday->user_id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-08-03']);
    $row = collect($week['rows'])->firstWhere('userId', $workday->user_id);

    expect($row['ordinaryDayHours'])->toBe('02:00:00')
        ->and($row['payableTotalHours'])->toBe('02:00:00')
        ->and($row['unauthorizedHours'])->toBe('00:00:00')
        ->and($row['capExceeded'])->toBeFalse();
});

test('a day with no authorisation record shows entirely as unauthorised, prominent and separate from the payable total', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    [$workday] = excessDay('2026-08-04', '01:30:00', $organization); // no OvertimeAuthorization opened

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$workday->user_id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-08-03']);
    $row = collect($week['rows'])->firstWhere('userId', $workday->user_id);

    expect($row['unauthorizedHours'])->toBe('01:30:00')
        ->and($row['payableTotalHours'])->toBe('00:00:00')
        ->and($row['ordinaryDayHours'])->toBe('00:00:00');
});

test('a week with both authorised and unauthorised hours splits pactada from no pactada using the classifier', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    [$authorized, $supervisor] = excessDay('2026-08-03', '01:00:00', $organization, $employee); // Monday
    OvertimeAuthorization::openFor($authorized)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    [$unauthorized] = excessDay('2026-08-04', '00:45:00', $organization, $employee); // Tuesday, never decided

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-08-03']);
    $row = collect($week['rows'])->firstWhere('userId', $employee->id);

    expect($row['ordinaryDayHours'])->toBe('01:00:00')
        ->and($row['unauthorizedHours'])->toBe('00:45:00')
        ->and($row['totalHours'])->toBe('01:45:00');
});

test('a week pushed over the weekly overtime cap is flagged for that employee only, never aggregated across a consolidated selection', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $overCapEmployee = User::factory()->for($organization)->employee()->create();
    $withinCapEmployee = User::factory()->for($organization)->employee()->create();

    // The over-cap employee: six weekdays at 2h/day authorised = 12h, plus a
    // Sunday half hour pushes the week's total to 12h30, past the 12h cap.
    foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-08'] as $date) {
        [$workday, $supervisor] = excessDay($date, '02:00:00', $organization, $overCapEmployee);
        OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');
    }
    [$sunday, $sundaySupervisor] = excessDay('2026-08-09', '00:30:00', $organization, $overCapEmployee);
    OvertimeAuthorization::openFor($sunday)->approve($sundaySupervisor, reason: 'Semana ya al tope; se autoriza igual por continuidad operativa.');

    // The within-cap employee: a single 1h day in the same week — well under
    // the cap on its own, and must stay unflagged even though the combined
    // company total for the week (13h30) would exceed 12h if ever summed.
    [$withinCapDay, $withinCapSupervisor] = excessDay('2026-08-03', '01:00:00', $organization, $withinCapEmployee);
    OvertimeAuthorization::openFor($withinCapDay)->approve($withinCapSupervisor, reason: 'Sin pacto vigente para esta fecha.');

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$overCapEmployee->id, $withinCapEmployee->id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-08-03']);

    $overCapRow = collect($week['rows'])->firstWhere('userId', $overCapEmployee->id);
    $withinCapRow = collect($week['rows'])->firstWhere('userId', $withinCapEmployee->id);

    expect($overCapRow['capExceeded'])->toBeTrue()
        ->and($withinCapRow['capExceeded'])->toBeFalse()
        ->and($week['employeesOverCapCount'])->toBe(1)
        ->and($week['weeklyOvertimeCapHours'])->toBe(12.0);
});

test('a week straddling the requested period boundary still renders in full', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    // 1 August 2026 is a Saturday — inside the requested month, but the
    // Monday-Sunday week it belongs to (27 Jul - 2 Aug) starts in July.
    [$workday, $supervisor] = excessDay('2026-08-01', '01:00:00', $organization, $employee);
    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-07-27']);

    expect($week)->not->toBeNull()
        ->and($week['end'])->toBe('2026-08-02');

    $row = collect($week['rows'])->firstWhere('userId', $employee->id);
    expect($row['ordinaryDayHours'])->toBe('01:00:00');
});

test('a consolidated selection shows one row per employee within the same week', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employeeOne = User::factory()->for($organization)->employee()->create();
    $employeeTwo = User::factory()->for($organization)->employee()->create();

    [$workdayOne, $supervisorOne] = excessDay('2026-08-03', '01:00:00', $organization, $employeeOne);
    OvertimeAuthorization::openFor($workdayOne)->approve($supervisorOne, reason: 'Sin pacto vigente para esta fecha.');

    [$workdayTwo, $supervisorTwo] = excessDay('2026-08-04', '02:00:00', $organization, $employeeTwo);
    OvertimeAuthorization::openFor($workdayTwo)->approve($supervisorTwo, reason: 'Sin pacto vigente para esta fecha.');

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$employeeOne->id, $employeeTwo->id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-08-03']);

    expect($week['rows'])->toHaveCount(2)
        ->and($week['total']['employeeCount'])->toBe(2)
        ->and($week['total']['payableTotalHours'])->toBe('03:00:00');
});

test('a single employee selection renders the same shape with one row per week', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    [$workday, $supervisor] = excessDay('2026-08-03', '01:00:00', $organization, $employee);
    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-08-03']);

    expect($week['rows'])->toHaveCount(1);
});

test('a cap change between the worked date and today is judged by the worked week Monday version', function () {
    Carbon::setTestNow('2026-08-09 10:00:00');

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    [$workday, $supervisor] = excessDay('2026-08-03', '02:00:00', $organization); // Monday
    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    // A stricter weekly cap takes effect from 5 Aug, after this week's Monday.
    app(LegalHourLimitVersions::class)->add([
        'effective_from' => '2026-08-05',
        'ordinary_weekly_hours' => 42,
        'ordinary_daily_hours' => 10,
        'max_overtime_daily_hours' => 1,
        'max_overtime_weekly_hours' => 1,
        'max_total_daily_hours' => 12,
        'max_total_weekly_hours' => 43,
        'legal_reference' => 'Ley ficticia',
    ]);

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$workday->user_id],
    ]));

    $props = $response->viewData('page')['props'];
    $week = excessWeek(['weeks' => $props['weeks'], 'week' => '2026-08-03']);
    $row = collect($week['rows'])->firstWhere('userId', $workday->user_id);

    // Under the stricter version 2h would breach a 1h cap; the week is judged
    // by its Monday (3 Aug), before the stricter version took effect.
    expect($week['weeklyOvertimeCapHours'])->toBe(12.0)
        ->and($row['capExceeded'])->toBeFalse();
});

test('selecting no employee shows no weeks', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl(['selectAll' => 0]));

    expect($response->viewData('page')['props']['weeks'])->toBe([]);
});

test('exporting is rejected for a csv format: this report only offers excel and pdf', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(overtimeExcessExportUrl('csv'))
        ->assertNotFound();
});

test('each export format streams a download with the correct content type', function (string $format, string $mime) {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    [$workday, $supervisor] = excessDay('2026-08-03', '01:00:00', $organization, $employee);
    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $response = $this->actingAs($admin)->get(overtimeExcessExportUrl($format, [
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($mime);
})->with([
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'pdf' => ['pdf', 'application/pdf'],
]);

test('the excel export contains the same payable total shown on screen', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    [$workday, $supervisor] = excessDay('2026-08-03', '02:00:00', $organization, $employee);
    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $query = ['selectAll' => 0, 'ids' => [$employee->id]];

    $excelResponse = $this->actingAs($admin)->get(overtimeExcessExportUrl('excel', $query));
    $sheet = overtimeExcessSpreadsheetFromXlsxResponse($excelResponse->baseResponse)->getActiveSheet();

    $found = false;
    foreach ($sheet->toArray() as $row) {
        if (in_array('02:00:00', $row, true)) {
            $found = true;
            break;
        }
    }

    expect($found)->toBeTrue();
});

test('every export is recorded in the payroll export activity log', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    excessDay('2026-08-03', '01:00:00', $organization, $employee);

    $this->actingAs($admin)->get(overtimeExcessExportUrl('pdf', [
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
        ->and($activity->properties['report_type'])->toBe('overtime-excess')
        ->and($activity->properties['format'])->toBe('pdf')
        ->and($activity->properties['employee_ids'])->toContain($employee->id);
});

test('a user without View:PayrollReport is forbidden from the overtime excess report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('payroll-reports.overtime-excess'))
        ->assertForbidden();
});

test('a user with View but not Export:PayrollReport cannot download the overtime excess report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->givePermissionTo('View:PayrollReport');

    $this->actingAs($user)
        ->get(route('payroll-reports.overtime-excess'))
        ->assertOk();

    $this->actingAs($user)
        ->get(overtimeExcessExportUrl('excel'))
        ->assertForbidden();
});

test('an employee from another organization is never blended into a requested selection', function () {
    $organizationOne = Organization::factory()->create();
    $organizationTwo = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organizationOne->id]);
    $admin->assignRole('admin');
    $otherEmployee = User::factory()->for($organizationTwo)->employee()->create();

    $response = $this->actingAs($admin)->get(overtimeExcessIndexUrl([
        'selectAll' => 0,
        'ids' => [$otherEmployee->id],
    ]));

    expect($response->viewData('page')['props']['weeks'])->toBe([]);
});
