<?php

use App\Enums\ReportPeriodType;
use App\Enums\WorkdayStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use App\Services\Reports\PayrollExportReadinessService;
use App\Services\Reports\PayrollPeriodSummaryService;
use App\Services\Reports\ReportEmployeeSelector;
use App\Support\EmployeeSelection;
use App\Support\ReportEmployeeFilters;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * KOL-19 AC #4: the shared filter's resolved selection — a period plus a
 * flat list of employee ids — must be consumed identically by the
 * aggregation service (KOL-13) and the integrity check (KOL-14). This proves
 * the hand-off directly: one `ReportPeriod` and one `ReportEmployeeSelector`
 * resolution feed both existing services with no adaptation in between.
 */
test('one resolved period and selection feeds both the aggregation service and the integrity check', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $this->actingAs($admin);

    $employee = User::factory()->for($organization)->employee()->create();
    Workday::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'date' => '2026-08-05',
        'status' => WorkdayStatus::Regular,
    ]);

    $period = new ReportPeriod(2026, 8, ReportPeriodType::FirstFortnight);
    $userIds = app(ReportEmployeeSelector::class)->resolve(
        new ReportEmployeeFilters,
        new EmployeeSelection(selectAll: true),
    );

    expect($userIds)->toBe([$employee->id]);

    $summary = app(PayrollPeriodSummaryService::class)
        ->build($period->start(), $period->end(), $userIds);
    $readiness = app(PayrollExportReadinessService::class)
        ->check($period->start(), $period->end(), $userIds);

    expect($summary->has($employee->id))->toBeTrue()
        ->and($readiness->isClean())->toBeTrue();
});
