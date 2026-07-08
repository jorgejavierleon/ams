<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Organization;
use App\Models\Premise;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user — an organization admin must belong to a tenant so that
        // org-scoped models (companies, positions, …) can be stamped with an
        // organization_id on creation.
        $organization = Organization::factory()->create([
            'name' => 'Demo Organization',
            'slug' => 'demo-organization',
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'admin',
            'organization_id' => $organization->id,
        ])->assignRole('admin');

        // A company the demo employees all belong to.
        $company = Company::factory()->create([
            'organization_id' => $organization->id,
            'social_reason' => 'Demo Company',
        ]);

        // Two branches (sucursales) for that company.
        $premises = collect(['Sucursal Centro', 'Sucursal Norte'])
            ->map(fn (string $name) => Premise::factory()
                ->forCompany($company)
                ->create(['name' => $name]));

        // Real, verifier-valid Chilean RUTs for the demo employees.
        $ruts = [
            '23415645-4', '22922483-2', '22901821-3', '22200751-8', '5564530-2',
            '6528495-2', '5241573-k', '6279151-9', '23189180-3', '19504854-1',
            '22519021-6', '24869917-5', '21437581-8', '24805654-1', '9801359-8',
            '10749051-5', '7364409-7', '14122478-6', '14825683-7', '8022394-3',
        ];

        // A handful of employees so the Employees list is populated for demos,
        // split evenly across the two branches, each with a valid RUT.
        User::factory()
            ->count(12)
            ->employee()
            ->create([
                'organization_id' => $organization->id,
                'company_id' => $company->id,
            ])
            ->each(fn (User $employee, int $index) => $employee->update([
                'premise_id' => $premises[$index % $premises->count()]->id,
                'rut' => $ruts[$index],
            ]));

        // DT inspector
        User::factory()->dtUser()->create([
            'name' => 'Usuario DT',
            'email' => 'admin@dt.gov.cl',
            'password' => 'admin',
        ]);

        // SaaS super-admin
        User::factory()->saasUser()->create([
            'name' => 'Usuario SaaS',
            'email' => 'super_admin@example.com',
            'password' => 'admin',
        ]);
    }
}
