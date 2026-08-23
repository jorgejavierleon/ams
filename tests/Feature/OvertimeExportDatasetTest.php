<?php

use App\Enums\OvertimeCalculationState;
use App\Enums\OvertimeCompensationType;
use App\Enums\OvertimeDayType;
use App\Models\Holiday;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use App\Services\Overtime\OvertimeExportDataset;
use App\Services\Overtime\RestDayBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A computed day carrying the given calculated overtime, its employee and a
 * supervisor from the same organization — mirrors the helper already used in
 * OvertimeAuthorizationTest/OvertimeRestDayBalanceTest, kept local so this
 * file stays self-contained.
 *
 * @return array{0: Workday, 1: User, 2: Organization}
 */
function exportDatasetDay(string $date, string $calculatedOvertime, ?Organization $organization = null, ?User $employee = null): array
{
    $organization ??= Organization::factory()->create();
    $employee ??= User::factory()->create(['organization_id' => $organization->id]);
    $supervisor = User::factory()->create(['organization_id' => $organization->id]);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse($date),
        'post_shift_excess' => $calculatedOvertime,
        'calculated_overtime' => $calculatedOvertime,
        'overtime_state' => OvertimeCalculationState::forCalculatedOvertime($calculatedOvertime),
        'overtime_calculated_at' => now(),
    ]);

    return [$workday, $supervisor, $organization];
}

// --- AC #1/#2: the happy path ---

test('the dataset returns one line per approved day, carrying every audit field', function () {
    [$workday, $supervisor] = exportDatasetDay('2026-08-04', '02:00:00'); // a Tuesday

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $lines = app(OvertimeExportDataset::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id]);

    expect($lines)->toHaveCount(1);

    $line = $lines->first();

    expect($line->userId)->toBe($workday->user_id)
        ->and($line->employeeRut)->toBe($authorization->user->formatted_rut ?? $authorization->user->rut)
        ->and($line->date->toDateString())->toBe('2026-08-04')
        ->and((string) $line->hours)->toBe('02:00:00')
        ->and($line->dayType)->toBe(OvertimeDayType::Weekday)
        ->and($line->pactReference)->toBeNull()
        ->and($line->approvedBy)->toBe($supervisor->id)
        ->and($line->approvedAt->toDateTimeString())->toBe($authorization->reviewed_at->toDateTimeString());
});

// --- AC #3: the structural impossibility, not the happy path ---

test('a pending record, a revoked record, and an active rest-day-compensated record can never reach the dataset', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);

    [$pendingWorkday, $supervisor] = exportDatasetDay('2026-08-03', '01:00:00', $organization, $employee);
    $pending = OvertimeAuthorization::openFor($pendingWorkday);

    [$revokedWorkday] = exportDatasetDay('2026-08-04', '01:00:00', $organization, $employee);
    OvertimeAuthorization::openFor($revokedWorkday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.')
        ->revoke($supervisor, 'Horas no autorizadas.');

    [$restDayWorkday] = exportDatasetDay('2026-08-05', '01:00:00', $organization, $employee);
    OvertimeAuthorization::openFor($restDayWorkday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.', compensationType: OvertimeCompensationType::RestDays);

    $lines = app(OvertimeExportDataset::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$employee->id]);

    expect($lines)->toBeEmpty();

    // Not merely absent from a mixed result — not present as a record at all,
    // proving there is no path from "pending" into a line.
    expect($pending->fresh()->isPending())->toBeTrue();
});

// --- AC #5: day type from Holiday data and the Sunday reasoning ---

test('a Sunday is reported with day type sunday', function () {
    [$workday, $supervisor] = exportDatasetDay('2026-08-02', '01:00:00'); // a Sunday

    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $lines = app(OvertimeExportDataset::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$workday->user_id]);

    expect($lines->first()->dayType)->toBe(OvertimeDayType::Sunday);
});

test('a public holiday is reported with day type holiday, taking precedence over the weekday', function () {
    $organization = Organization::factory()->create();
    Holiday::factory()->create(['date' => '2026-09-18', 'organization_id' => null]); // a Friday

    [$workday, $supervisor] = exportDatasetDay('2026-09-18', '01:00:00', $organization);

    OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');

    $lines = app(OvertimeExportDataset::class)
        ->forPeriod(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), [$workday->user_id]);

    expect($lines->first()->dayType)->toBe(OvertimeDayType::Holiday);
});

// --- AC #4: rest-day compensation, once expired unconsumed, becomes payable as a second source ---

test('an expired, unconsumed rest-day balance appears as a payable line dated by its expiry, keeping the original day type', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id, 'overtime_rest_day_eligible' => true]);
    Holiday::factory()->create(['date' => '2026-01-01', 'organization_id' => null]);

    [$workday, $supervisor] = exportDatasetDay('2026-01-01', '02:00:00', $organization, $employee);

    $authorization = OvertimeAuthorization::openFor($workday)
        ->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.', compensationType: OvertimeCompensationType::RestDays);

    // Six months later the balance is still untouched, so it lapses. Its
    // expiry_date (2026-07-01) is what falls inside the period below, not the
    // original 2026-01-01 workday.
    Carbon::setTestNow('2026-07-15');
    app(RestDayBalanceService::class)->sweepExpired();

    $lines = app(OvertimeExportDataset::class)
        ->forPeriod(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'), [$employee->id]);

    Carbon::setTestNow();

    expect($lines)->toHaveCount(1);

    $line = $lines->first();

    expect($line->date->toDateString())->toBe('2026-07-01')
        ->and((string) $line->hours)->toBe('02:00:00')
        ->and($line->dayType)->toBe(OvertimeDayType::Holiday)
        ->and($line->approvedBy)->toBe($supervisor->id);

    // The original authorization itself never becomes exportable — this line
    // comes from the balance, a distinct source, never from the row above.
    expect(OvertimeAuthorization::exportable()->find($authorization->id))->toBeNull();
});

// --- AC #6: organization scoping and bounded query count ---

test('an employee from another organization never appears, even when explicitly requested', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $employeeA = User::factory()->create(['organization_id' => $orgA->id]);
    $adminA = User::factory()->create(['organization_id' => $orgA->id]);
    [$workdayA, $supervisorA] = exportDatasetDay('2026-08-04', '01:00:00', $orgA, $employeeA);
    OvertimeAuthorization::openFor($workdayA)->approve($supervisorA, reason: 'Sin pacto vigente para esta fecha.');

    $employeeB = User::factory()->create(['organization_id' => $orgB->id]);
    [$workdayB, $supervisorB] = exportDatasetDay('2026-08-04', '01:00:00', $orgB, $employeeB);
    OvertimeAuthorization::openFor($workdayB)->approve($supervisorB, reason: 'Sin pacto vigente para esta fecha.');

    $this->actingAs($adminA);

    $lines = app(OvertimeExportDataset::class)
        ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$employeeA->id, $employeeB->id]);

    expect($lines)->toHaveCount(1)
        ->and($lines->first()->userId)->toBe($employeeA->id);
});

test('the query count for the dataset does not grow with the number of employees', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $this->actingAs($admin);

    $buildEmployees = function (int $count) use ($organization): array {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $employee = User::factory()->create(['organization_id' => $organization->id]);
            [$workday, $supervisor] = exportDatasetDay('2026-08-04', '01:00:00', $organization, $employee);
            OvertimeAuthorization::openFor($workday)->approve($supervisor, reason: 'Sin pacto vigente para esta fecha.');
            $ids[] = $employee->id;
        }

        return $ids;
    };

    $countQueries = function (array $userIds): int {
        $queries = 0;
        $listener = function () use (&$queries): void {
            $queries++;
        };

        DB::listen($listener);
        app(OvertimeExportDataset::class)
            ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), $userIds);

        return $queries;
    };

    $small = $countQueries($buildEmployees(3));
    $large = $countQueries($buildEmployees(30));

    expect($large)->toBe($small);
});
