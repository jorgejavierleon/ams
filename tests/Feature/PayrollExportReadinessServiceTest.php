<?php

use App\Enums\PayrollExportFindingType;
use App\Enums\WorkdayStatus;
use App\Models\Incident;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use App\Services\Reports\PayrollExportReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('a clean period produces no findings and needs no confirmation', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-03'),
        'status' => WorkdayStatus::Regular,
    ]);

    $readiness = app(PayrollExportReadinessService::class)
        ->check(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-15'), [$employee->id]);

    expect($readiness->isClean())->toBeTrue()
        ->and($readiness->requiresConfirmation())->toBeFalse()
        ->and($readiness->findings)->toHaveCount(0);
});

test('a pending mark modification is a blocking finding linking back to the workday', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    $workday = Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-05'),
        'status' => WorkdayStatus::Regular,
    ]);
    MarkModification::factory()->for($organization)->create([
        'workday_id' => $workday->id,
        'user_id' => $employee->id,
    ]);

    $readiness = app(PayrollExportReadinessService::class)
        ->check(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-15'), [$employee->id]);

    expect($readiness->isClean())->toBeFalse()
        ->and($readiness->requiresConfirmation())->toBeTrue()
        ->and($readiness->findings)->toHaveCount(1);

    $finding = $readiness->findings->first();

    expect($finding->type)->toBe(PayrollExportFindingType::PendingMarkModification)
        ->and($finding->userId)->toBe($employee->id)
        ->and($finding->date->toDateString())->toBe('2026-08-05')
        ->and($finding->blocking())->toBeTrue()
        ->and($finding->resolutionUrl)->toBe(route('workdays.show', $workday));
});

test('irregular and incomplete workdays are both detected as blocking findings', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-05'),
        'status' => WorkdayStatus::Irregular,
    ]);
    Workday::factory()->incomplete()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-06'),
    ]);
    // A regular day in between must not surface as a finding.
    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-07'),
        'status' => WorkdayStatus::Regular,
    ]);

    $readiness = app(PayrollExportReadinessService::class)
        ->check(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-15'), [$employee->id]);

    $types = $readiness->findings->map(fn ($finding) => $finding->type)->all();

    expect($readiness->findings)->toHaveCount(2)
        ->and($types)->toContain(PayrollExportFindingType::IrregularWorkday)
        ->and($types)->toContain(PayrollExportFindingType::IncompleteWorkday)
        ->and($readiness->requiresConfirmation())->toBeTrue();
});

test('an open technical incident overlapping the period is informational only and never blocks alone', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    Incident::factory()->for($organization)->create([
        'start_time' => Carbon::parse('2026-08-04 09:00:00'),
        'end_time' => null,
    ]);
    // A closed incident must not surface.
    Incident::factory()->for($organization)->create([
        'start_time' => Carbon::parse('2026-08-02 09:00:00'),
        'end_time' => Carbon::parse('2026-08-02 10:00:00'),
    ]);
    // An open incident starting after the period ends is irrelevant.
    Incident::factory()->for($organization)->create([
        'start_time' => Carbon::parse('2026-09-01 09:00:00'),
        'end_time' => null,
    ]);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-04'),
        'status' => WorkdayStatus::Regular,
    ]);

    $readiness = app(PayrollExportReadinessService::class)
        ->check(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-15'), [$employee->id]);

    expect($readiness->isClean())->toBeFalse()
        ->and($readiness->findings)->toHaveCount(1);

    $finding = $readiness->findings->first();

    expect($finding->type)->toBe(PayrollExportFindingType::OpenIncident)
        ->and($finding->blocking())->toBeFalse()
        ->and($finding->userId)->toBeNull()
        ->and($readiness->requiresConfirmation())->toBeFalse();
});

test('an employee from another organization never appears, even when explicitly requested', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $adminA = User::factory()->create(['organization_id' => $orgA->id]);
    $employeeA = User::factory()->for($orgA)->employee()->create();
    $employeeB = User::factory()->for($orgB)->employee()->create();

    Workday::factory()->create([
        'organization_id' => $orgA->id,
        'user_id' => $employeeA->id,
        'date' => Carbon::parse('2026-08-05'),
        'status' => WorkdayStatus::Irregular,
    ]);
    Workday::factory()->create([
        'organization_id' => $orgB->id,
        'user_id' => $employeeB->id,
        'date' => Carbon::parse('2026-08-05'),
        'status' => WorkdayStatus::Irregular,
    ]);

    $this->actingAs($adminA);

    $readiness = app(PayrollExportReadinessService::class)
        ->check(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), [$employeeA->id, $employeeB->id]);

    expect($readiness->findings)->toHaveCount(1)
        ->and($readiness->findings->first()->userId)->toBe($employeeA->id);
});

test('the query count does not grow with the number of employees', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $this->actingAs($admin);

    $buildEmployees = function (int $count) use ($organization): array {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $employee = User::factory()->for($organization)->employee()->create();
            $workday = Workday::factory()->create([
                'organization_id' => $organization->id,
                'user_id' => $employee->id,
                'date' => Carbon::parse('2026-08-05'),
                'status' => WorkdayStatus::Irregular,
            ]);
            MarkModification::factory()->for($organization)->create([
                'workday_id' => $workday->id,
                'user_id' => $employee->id,
            ]);
            $ids[] = $employee->id;
        }

        return $ids;
    };

    $countQueries = function (array $userIds): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        app(PayrollExportReadinessService::class)
            ->check(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), $userIds);

        return $queries;
    };

    $small = $countQueries($buildEmployees(3));
    $large = $countQueries($buildEmployees(30));

    expect($large)->toBe($small);
});

test('confirming an export with unresolved findings is recorded in the activity log', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $employee = User::factory()->for($organization)->employee()->create();
    $this->actingAs($admin);

    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => Carbon::parse('2026-08-05'),
        'status' => WorkdayStatus::Irregular,
    ]);

    $service = app(PayrollExportReadinessService::class);
    $start = Carbon::parse('2026-08-01');
    $end = Carbon::parse('2026-08-15');
    $readiness = $service->check($start, $end, [$employee->id]);

    $service->recordConfirmation($admin, $start, $end, $readiness);

    $activity = Activity::query()->where('log_name', 'payroll_export')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['organization_id'])->toBe($organization->id)
        ->and($activity->properties['period_start'])->toBe('2026-08-01')
        ->and($activity->properties['period_end'])->toBe('2026-08-15')
        ->and($activity->properties['employee_ids'])->toContain($employee->id)
        ->and($activity->properties['finding_types'])->toContain('irregular_workday');
});
