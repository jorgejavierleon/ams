<?php

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\WorkdayStatus;
use App\Models\Leave;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\User;
use App\Models\Workday;
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

function weeklyDetailIndexUrl(array $query = []): string
{
    return route('payroll-reports.weekly-detail', [
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'month',
        ...$query,
    ]);
}

function weeklyDetailExportUrl(string $format, array $query = []): string
{
    return route('payroll-reports.weekly-detail.export', [
        'format' => $format,
        'period_year' => 2026,
        'period_month' => 8,
        'period_type' => 'month',
        ...$query,
    ]);
}

function weeklyDetailStreamedContent(Response $response): string
{
    return TestResponse::fromBaseResponse($response)->streamedContent();
}

function weeklyDetailSpreadsheetFromXlsxResponse(Response $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, weeklyDetailStreamedContent($response));

    try {
        return (new XlsxReader)->load($path);
    } finally {
        unlink($path);
    }
}

test('a normal week shows real entrada/salida against the theoretical shift with their differences', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
        'shift_start_time' => '08:00:00',
        'shift_end_time' => '17:00:00',
        'mark_in_at' => '2026-08-03 08:02:00',
        'mark_out_at' => '2026-08-03 17:05:00',
        'in_time_difference' => '00:02:00',
        'out_time_difference' => '00:05:00',
        'status' => WorkdayStatus::Regular,
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['employee']['id'])->toBe($employee->id);

    $day = collect($props['weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-08-03');

    expect($day['status_label'])->toBe(WorkdayStatus::Regular->label())
        ->and($day['entry']['real'])->toBe('08:02:00')
        ->and($day['entry']['theoretical'])->toBe('08:00')
        ->and($day['entry']['difference'])->toBe('+00:02:00')
        ->and($day['exit']['real'])->toBe('17:05:00')
        ->and($day['exit']['theoretical'])->toBe('17:00')
        ->and($day['exit']['difference'])->toBe('+00:05:00');
});

test('an absence day renders sensibly with no real marks', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Workday::factory()->absent()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-04',
        'shift_start_time' => '08:00:00',
        'shift_end_time' => '17:00:00',
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $day = collect($response->viewData('page')['props']['weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-08-04');

    expect($day['status_label'])->toBe(WorkdayStatus::Absent->label())
        ->and($day['entry']['real'])->toBeNull()
        ->and($day['exit']['real'])->toBeNull();
});

test('an incomplete day shows the one mark it has and leaves the other blank', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Workday::factory()->incomplete()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-05',
        'mark_in_at' => '2026-08-05 08:00:00',
        'shift_start_time' => '08:00:00',
        'shift_end_time' => '17:00:00',
        'in_time_difference' => '00:00:00',
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $day = collect($response->viewData('page')['props']['weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-08-05');

    expect($day['status_label'])->toBe(WorkdayStatus::Incomplete->label())
        ->and($day['entry']['real'])->toBe('08:00:00')
        ->and($day['exit']['real'])->toBeNull();
});

test('a day on leave surfaces the leave type', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $leave = Leave::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'start_date' => '2026-08-06',
        'end_date' => '2026-08-06',
        'status' => LeaveStatus::Approved,
        'type' => LeaveType::Vacation,
    ]);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-06',
        'leave_id' => $leave->id,
        'mark_in_at' => null,
        'mark_out_at' => null,
        'mark_in_id' => null,
        'mark_out_id' => null,
        'in_time_difference' => null,
        'out_time_difference' => null,
        'status' => WorkdayStatus::Justified,
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $day = collect($response->viewData('page')['props']['weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-08-06');

    expect($day['leave']['type_label'])->toBe(LeaveType::Vacation->label());
});

test('the theoretical colacion window comes from the assigned shift day', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $shift = Shift::factory()->for($organization)->create();
    ShiftDay::factory()->for($shift)->create([
        'weekday' => 0,
        'lunch_start_time' => '12:00:00',
        'lunch_end_time' => '13:00:00',
    ]);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
        'shift_id' => $shift->id,
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $day = collect($response->viewData('page')['props']['weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-08-03');

    expect($day['lunch']['theoretical_start'])->toBe('12:00')
        ->and($day['lunch']['theoretical_end'])->toBe('13:00')
        ->and($day['lunch']['real'])->toBeNull();
});

test('a pending mark modification is surfaced on its day', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
    ]);

    MarkModification::factory()->for($organization)->create([
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $day = collect($response->viewData('page')['props']['weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-08-03');

    expect($day['has_pending_modification'])->toBeTrue()
        ->and($day['has_approved_modification'])->toBeFalse();
});

test('an approved mark modification is surfaced on its day', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
    ]);

    MarkModification::factory()->approved()->for($organization)->create([
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $day = collect($response->viewData('page')['props']['weeks'])
        ->flatMap(fn (array $week) => $week['days'])
        ->firstWhere('date', '2026-08-03');

    expect($day['has_approved_modification'])->toBeTrue();
});

test('selecting no employee shows no report and requires narrowing the selection', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl(['selectAll' => 0]));

    $props = $response->viewData('page')['props'];

    expect($props['employee'])->toBeNull()
        ->and($props['weeks'])->toBe([]);
});

test('selecting more than one employee shows no report either', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employeeOne = User::factory()->for($organization)->employee()->create();
    $employeeTwo = User::factory()->for($organization)->employee()->create();

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$employeeOne->id, $employeeTwo->id],
    ]));

    $props = $response->viewData('page')['props'];

    expect($props['employee'])->toBeNull()
        ->and($props['weeks'])->toBe([]);
});

test('exporting without exactly one employee selected is rejected', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(weeklyDetailExportUrl('excel', ['selectAll' => 0]))
        ->assertStatus(422);
});

test('each export format streams a download with the correct content type', function (string $format, string $mime) {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-08-03 08:00:00',
    ]);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
    ]);

    $response = $this->actingAs($admin)->get(weeklyDetailExportUrl($format, [
        'selectAll' => 0,
        'ids' => [$employee->id],
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($mime);
})->with([
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'pdf' => ['pdf', 'application/pdf'],
]);

test('a csv export is rejected: this report only offers excel and pdf', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(weeklyDetailExportUrl('csv'))
        ->assertNotFound();
});

test('the excel export contains the same entrada time shown on screen', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');
    $employee = User::factory()->for($organization)->employee()->create();

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
        'mark_in_at' => '2026-08-03 08:02:00',
    ]);

    $query = ['selectAll' => 0, 'ids' => [$employee->id]];

    $excelResponse = $this->actingAs($admin)->get(weeklyDetailExportUrl('excel', $query));
    $sheet = weeklyDetailSpreadsheetFromXlsxResponse($excelResponse->baseResponse)->getActiveSheet();

    $found = false;
    foreach ($sheet->toArray() as $row) {
        if (in_array('08:02:00', $row, true)) {
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

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-03',
    ]);

    $this->actingAs($admin)->get(weeklyDetailExportUrl('pdf', [
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
        ->and($activity->properties['report_type'])->toBe('weekly-detail')
        ->and($activity->properties['format'])->toBe('pdf')
        ->and($activity->properties['employee_ids'])->toContain($employee->id);
});

test('a user without View:PayrollReport is forbidden from the weekly detail report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('payroll-reports.weekly-detail'))
        ->assertForbidden();
});

test('a user with View but not Export:PayrollReport cannot download the weekly detail report', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->givePermissionTo('View:PayrollReport');

    $this->actingAs($user)
        ->get(route('payroll-reports.weekly-detail'))
        ->assertOk();

    $this->actingAs($user)
        ->get(weeklyDetailExportUrl('excel'))
        ->assertForbidden();
});

test('an employee from another organization cannot be resolved into the report', function () {
    $organizationOne = Organization::factory()->create();
    $organizationTwo = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organizationOne->id]);
    $admin->assignRole('admin');
    $otherEmployee = User::factory()->for($organizationTwo)->employee()->create();

    $response = $this->actingAs($admin)->get(weeklyDetailIndexUrl([
        'selectAll' => 0,
        'ids' => [$otherEmployee->id],
    ]));

    $props = $response->viewData('page')['props'];

    expect($props['employee'])->toBeNull()
        ->and($props['weeks'])->toBe([]);
});
