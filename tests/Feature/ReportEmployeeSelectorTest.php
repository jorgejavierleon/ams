<?php

use App\Enums\ContractType;
use App\Models\CostCenter;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use App\Services\Reports\ReportEmployeeSelector;
use App\Support\EmployeeSelection;
use App\Support\ReportEmployeeFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportSelectorOrg(): Organization
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    test()->actingAs($admin);

    return $organization;
}

test('selecting nothing resolves to an empty list rather than the whole company', function () {
    $organization = reportSelectorOrg();
    User::factory()->for($organization)->employee()->create();

    $resolved = app(ReportEmployeeSelector::class)->resolve(
        new ReportEmployeeFilters,
        new EmployeeSelection(selectAll: false, ids: []),
    );

    expect($resolved)->toBe([]);
});

test('select-all resolves to every candidate matching the filters', function () {
    $organization = reportSelectorOrg();
    $a = User::factory()->for($organization)->employee()->create();
    $b = User::factory()->for($organization)->employee()->create();

    $resolved = app(ReportEmployeeSelector::class)->resolve(
        new ReportEmployeeFilters,
        new EmployeeSelection(selectAll: true, ids: []),
    );

    expect($resolved)->toEqualCanonicalizing([$a->id, $b->id]);
});

test('select-all excludes the ids named in the selection', function () {
    $organization = reportSelectorOrg();
    $kept = User::factory()->for($organization)->employee()->create();
    $excluded = User::factory()->for($organization)->employee()->create();

    $resolved = app(ReportEmployeeSelector::class)->resolve(
        new ReportEmployeeFilters,
        new EmployeeSelection(selectAll: true, ids: [$excluded->id]),
    );

    expect($resolved)->toBe([$kept->id]);
});

test('an exclusion survives the filter set changing', function () {
    $organization = reportSelectorOrg();
    $premiseA = Premise::factory()->for($organization)->create();
    $premiseB = Premise::factory()->for($organization)->create();
    $kept = User::factory()->for($organization)->employee()->create(['premise_id' => $premiseA->id]);
    $excluded = User::factory()->for($organization)->employee()->create(['premise_id' => $premiseA->id]);
    $elsewhere = User::factory()->for($organization)->employee()->create(['premise_id' => $premiseB->id]);

    $selection = new EmployeeSelection(selectAll: true, ids: [$excluded->id]);
    $selector = app(ReportEmployeeSelector::class);

    $beforeFilterChange = $selector->resolve(
        new ReportEmployeeFilters(premiseIds: [$premiseA->id]),
        $selection,
    );
    expect($beforeFilterChange)->toBe([$kept->id]);

    // Widening the filter to include premiseB must still respect the same
    // exclusion, without the caller having to recompute it (AC #3).
    $afterFilterChange = $selector->resolve(
        new ReportEmployeeFilters(premiseIds: [$premiseA->id, $premiseB->id]),
        $selection,
    );
    expect($afterFilterChange)->toEqualCanonicalizing([$kept->id, $elsewhere->id]);
});

test('each filter dimension narrows the candidate pool', function () {
    $organization = reportSelectorOrg();
    $premise = Premise::factory()->for($organization)->create();
    $costCenter = CostCenter::factory()->for($organization)->create();
    $position = Position::factory()->for($organization)->create();

    $match = User::factory()->for($organization)->employee()->create([
        'premise_id' => $premise->id,
        'cost_center_id' => $costCenter->id,
        'position_id' => $position->id,
        'contract_type' => ContractType::Indefinido,
    ]);
    User::factory()->for($organization)->employee()->create([
        'contract_type' => ContractType::PlazoFijo,
    ]);

    $selector = app(ReportEmployeeSelector::class);

    expect($selector->resolve(new ReportEmployeeFilters(premiseIds: [$premise->id]), new EmployeeSelection(true)))
        ->toBe([$match->id])
        ->and($selector->resolve(new ReportEmployeeFilters(costCenterIds: [$costCenter->id]), new EmployeeSelection(true)))
        ->toBe([$match->id])
        ->and($selector->resolve(new ReportEmployeeFilters(positionIds: [$position->id]), new EmployeeSelection(true)))
        ->toBe([$match->id])
        ->and($selector->resolve(new ReportEmployeeFilters(contractTypes: [ContractType::Indefinido]), new EmployeeSelection(true)))
        ->toBe([$match->id]);
});

test('a manual pick is independent of the filter dimensions', function () {
    $organization = reportSelectorOrg();
    $picked = User::factory()->for($organization)->employee()->create();
    $otherPremise = Premise::factory()->for($organization)->create();

    $resolved = app(ReportEmployeeSelector::class)->resolve(
        new ReportEmployeeFilters(premiseIds: [$otherPremise->id]),
        new EmployeeSelection(selectAll: false, ids: [$picked->id]),
    );

    expect($resolved)->toBe([$picked->id]);
});

test('an employee from another organization can never be selected', function () {
    $organization = reportSelectorOrg();
    $otherOrganization = Organization::factory()->create();
    $foreignEmployee = User::factory()->for($otherOrganization)->employee()->create();
    User::factory()->for($organization)->employee()->create();

    $selector = app(ReportEmployeeSelector::class);

    $viaSelectAll = $selector->resolve(new ReportEmployeeFilters, new EmployeeSelection(true, [$foreignEmployee->id]));
    expect($viaSelectAll)->not->toContain($foreignEmployee->id);

    $viaManualPick = $selector->resolve(new ReportEmployeeFilters, new EmployeeSelection(false, [$foreignEmployee->id]));
    expect($viaManualPick)->toBe([]);
});

test('the facet options are scoped to the current organization', function () {
    $organization = reportSelectorOrg();
    $otherOrganization = Organization::factory()->create();
    $premise = Premise::factory()->for($organization)->create(['name' => 'Sucursal Propia']);
    Premise::factory()->for($otherOrganization)->create(['name' => 'Sucursal Ajena']);

    $options = app(ReportEmployeeSelector::class)->optionsFor();

    expect(collect($options['premises'])->pluck('label')->all())
        ->toBe([$premise->name]);
});

test('each facet option carries how many employees currently have that value', function () {
    $organization = reportSelectorOrg();
    $premise = Premise::factory()->for($organization)->create();
    $otherPremise = Premise::factory()->for($organization)->create();
    User::factory()->for($organization)->employee()->count(2)->create(['premise_id' => $premise->id]);
    User::factory()->for($organization)->employee()->create(['premise_id' => $otherPremise->id]);
    User::factory()->for($organization)->employee()->count(3)->create(['contract_type' => ContractType::Honorarios]);

    $options = app(ReportEmployeeSelector::class)->optionsFor();

    $premiseCounts = collect($options['premises'])->keyBy('value');
    expect($premiseCounts->get((string) $premise->id)['count'])->toBe(2)
        ->and($premiseCounts->get((string) $otherPremise->id)['count'])->toBe(1);

    $contractTypeCounts = collect($options['contractTypes'])->keyBy('value');
    expect($contractTypeCounts->get(ContractType::Honorarios->value)['count'])->toBe(3);
});
