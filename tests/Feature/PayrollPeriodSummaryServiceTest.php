<?php

use App\Enums\LeaveType;
use App\Enums\OvertimeCalculationState;
use App\Events\WorkdaysRecalculationNeeded;
use App\Models\Leave;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use App\Services\Reports\PayrollPeriodSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * A worked day: a mark (so AttendanceReportService counts it as attended) and
 * a matching Workday row (so the hour totals have something to sum), mirroring
 * how the two data sources actually relate in production.
 */
function summaryWorkedDay(Organization $organization, User $employee, string $date, string $workedTime = '08:00:00'): Workday
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
    ]);
}

test('an employee with approved overtime is summarized with worked hours and the overtime routed to its pay bucket', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);
    $this->actingAs($admin);

    summaryWorkedDay($organization, $employee, '2026-08-03'); // a Monday

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-04'), // a Tuesday
        'worked_time' => '10:00:00',
        'post_shift_excess' => '02:00:00',
        'calculated_overtime' => '02:00:00',
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime('02:00:00'),
        'overtime_calculated_at' => now(),
    ]);
    Mark::factory()->for($organization)->create(['user_id' => $employee->id, 'date_time' => '2026-08-04 08:00:00']);
    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-03'), Carbon::parse('2026-08-04'), [$employee->id])
        ->get($employee->id);

    expect((string) $summary->workedHours)->toBe('18:00:00')
        ->and((string) $summary->overtime->ordinaryDayHours)->toBe('02:00:00')
        ->and($summary->overtime->sundayOrHolidayHours->isZero())->toBeTrue()
        ->and($summary->paidDays->workedDays)->toBe(2)
        ->and($summary->unjustifiedAbsenceDays)->toBe(0);
});

test('an employee with unjustified absences has them counted as non-paid days', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    summaryWorkedDay($organization, $employee, '2026-08-03'); // a Monday, attended
    // 2026-08-04 and 2026-08-05: no mark, no shift, no leave -> unjustified.

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-03'), Carbon::parse('2026-08-05'), [$employee->id])
        ->get($employee->id);

    expect($summary->unjustifiedAbsenceDays)->toBe(2)
        ->and($summary->nonPaidDays->unjustifiedAbsenceDays)->toBe(2)
        ->and($summary->paidDays->workedDays)->toBe(1)
        ->and($summary->justifiedAbsenceDays)->toBe(0);
});

test('an employee on approved medical leave has the days counted under non-paid days / medical leave', function () {
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    Leave::factory()->for($organization)->approved()->medical()->create([
        'user_id' => $employee->id,
        'start_date' => '2026-08-06',
        'end_date' => '2026-08-07',
    ]);

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-06'), Carbon::parse('2026-08-07'), [$employee->id])
        ->get($employee->id);

    expect($summary->justifiedAbsenceDays)->toBe(2)
        ->and($summary->nonPaidDays->medicalLeaveDays)->toBe(2)
        ->and($summary->paidDays->vacationDays)->toBe(0)
        ->and($summary->unjustifiedAbsenceDays)->toBe(0);
});

test('an employee on approved vacation has the days counted under paid days / vacation days', function () {
    Event::fake([WorkdaysRecalculationNeeded::class]);

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    Leave::factory()->for($organization)->approved()->create([
        'user_id' => $employee->id,
        'type' => LeaveType::Vacation,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-11',
    ]);

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-10'), Carbon::parse('2026-08-11'), [$employee->id])
        ->get($employee->id);

    expect($summary->justifiedAbsenceDays)->toBe(2)
        ->and($summary->paidDays->vacationDays)->toBe(2)
        ->and($summary->nonPaidDays->medicalLeaveDays)->toBe(0);
});

test('an employee with no data in the period returns an all-zero summary rather than being dropped', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-15'), [$employee->id])
        ->get($employee->id);

    expect($summary->workedHours->isZero())->toBeTrue()
        ->and($summary->nonWorkedHours->isZero())->toBeTrue()
        ->and($summary->totalLateness->isZero())->toBeTrue()
        ->and($summary->overtime->ordinaryDayHours->isZero())->toBeTrue()
        ->and($summary->paidDays->workedDays)->toBe(0)
        // Every remaining day in the range has no shift, no mark, no leave: unjustified.
        ->and($summary->unjustifiedAbsenceDays)->toBe(15);
});

test('a Sunday worked is counted in sundaysAndHolidaysWorked', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    summaryWorkedDay($organization, $employee, '2026-08-02'); // a Sunday

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-02'), [$employee->id])
        ->get($employee->id);

    expect($summary->sundaysAndHolidaysWorked)->toBe(1);
});

test('total lateness only sums late arrivals, never early ones', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    Mark::factory()->for($organization)->create(['user_id' => $employee->id, 'date_time' => '2026-08-03 08:05:00']);
    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-03'),
        'in_time_difference' => '00:05:00', // 5 minutes late
    ]);

    Mark::factory()->for($organization)->create(['user_id' => $employee->id, 'date_time' => '2026-08-04 07:55:00']);
    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-04'),
        'in_time_difference' => '-00:05:00', // 5 minutes early
    ]);

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-03'), Carbon::parse('2026-08-04'), [$employee->id])
        ->get($employee->id);

    expect((string) $summary->totalLateness)->toBe('00:05:00');
});

test('the summary can be produced for a quincena that does not align to month boundaries', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    summaryWorkedDay($organization, $employee, '2026-08-20');

    $summary = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-16'), Carbon::parse('2026-08-30'), [$employee->id])
        ->get($employee->id);

    expect((string) $summary->workedHours)->toBe('08:00:00')
        ->and($summary->paidDays->workedDays)->toBe(1);
});

test('an employee from another organization never appears, even when explicitly requested', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $adminA = User::factory()->create(['organization_id' => $orgA->id]);
    $employeeA = User::factory()->for($orgA)->employee()->create();
    $employeeB = User::factory()->for($orgB)->employee()->create();

    summaryWorkedDay($orgA, $employeeA, '2026-08-04');
    summaryWorkedDay($orgB, $employeeB, '2026-08-04');

    $this->actingAs($adminA);

    $summaries = app(PayrollPeriodSummaryService::class)
        ->build(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$employeeA->id, $employeeB->id]);

    expect($summaries)->toHaveCount(1)
        ->and($summaries->has($employeeB->id))->toBeFalse();
});

test('the query count for the summary does not grow with the number of employees', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $this->actingAs($admin);

    $buildEmployees = function (int $count) use ($organization): array {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $employee = User::factory()->for($organization)->employee()->create();
            summaryWorkedDay($organization, $employee, '2026-08-04');
            $ids[] = $employee->id;
        }

        return $ids;
    };

    $countQueries = function (array $userIds): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        app(PayrollPeriodSummaryService::class)
            ->build(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), $userIds);

        return $queries;
    };

    $small = $countQueries($buildEmployees(3));
    $large = $countQueries($buildEmployees(30));

    expect($large)->toBe($small);
});
