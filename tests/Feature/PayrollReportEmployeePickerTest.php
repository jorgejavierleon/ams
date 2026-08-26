<?php

use App\Models\Organization;
use App\Models\Premise;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function payrollReportPickerAdmin(Organization $organization): User
{
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

test('the picker only lists employees from the current organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $admin = payrollReportPickerAdmin($organization);

    $ownEmployee = User::factory()->for($organization)->employee()->create();
    $foreignEmployee = User::factory()->for($otherOrganization)->employee()->create();

    $this->actingAs($admin)
        ->get(route('payroll-reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where(
                'employees.data',
                fn ($employees) => collect($employees)->pluck('id')->contains($ownEmployee->id)
                    && ! collect($employees)->pluck('id')->contains($foreignEmployee->id),
            )
        );
});

test('the picker filters by premise via the same route', function () {
    $organization = Organization::factory()->create();
    $admin = payrollReportPickerAdmin($organization);
    $premise = Premise::factory()->for($organization)->create();
    $matching = User::factory()->for($organization)->employee()->create(['premise_id' => $premise->id]);
    $other = User::factory()->for($organization)->employee()->create();

    $this->actingAs($admin)
        ->get(route('payroll-reports.index', ['premises' => [$premise->id]]))
        ->assertInertia(fn ($page) => $page
            ->where('employees.data', function ($employees) use ($matching, $other) {
                $ids = collect($employees)->pluck('id');

                return $ids->contains($matching->id) && ! $ids->contains($other->id);
            })
        );
});

test('filter option lists are scoped to the current organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $admin = payrollReportPickerAdmin($organization);
    Premise::factory()->for($organization)->create(['name' => 'Sucursal Propia']);
    Premise::factory()->for($otherOrganization)->create(['name' => 'Sucursal Ajena']);

    $this->actingAs($admin)
        ->get(route('payroll-reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where(
                'filterOptions.premises',
                fn ($premises) => collect($premises)->pluck('label')->all() === ['Sucursal Propia'],
            )
        );
});

test('period type options are exposed for the period selector', function () {
    $organization = Organization::factory()->create();
    $admin = payrollReportPickerAdmin($organization);

    $this->actingAs($admin)
        ->get(route('payroll-reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where(
                'periodTypeOptions',
                fn ($options) => collect($options)->pluck('value')->all() === ['month', 'first_fortnight', 'second_fortnight'],
            )
        );
});
