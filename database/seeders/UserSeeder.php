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

        // Two legal representatives for the demo company. They are flagged
        // `is_legal_rep` so they are picked up as document co-signatories (the
        // `{{legal_rep_name}}` template variable resolves to the first of them),
        // and carry the `employee` role so they hold the self-service document
        // permissions needed to actually sign from the "Mis documentos" panel.
        collect([
            ['name' => 'Representante Legal Uno', 'first' => 'Representante', 'last' => 'Legal Uno', 'email' => 'legal-rep-1@example.com', 'personal_email' => 'legal-rep-1.personal@example.com', 'rut' => '9801359-8'],
            ['name' => 'Representante Legal Dos', 'first' => 'Representante', 'last' => 'Legal Dos', 'email' => 'legal-rep-2@example.com', 'personal_email' => 'legal-rep-2.personal@example.com', 'rut' => '10749051-5'],
        ])->each(fn (array $legalRep) => User::factory()->employee()->create([
            'name' => $legalRep['name'],
            'first_name' => $legalRep['first'],
            'last_name' => $legalRep['last'],
            'email' => $legalRep['email'],
            'personal_email' => $legalRep['personal_email'],
            'password' => 'admin',
            'organization_id' => $organization->id,
            'company_id' => $company->id,
            'premise_id' => $premises->first()->id,
            'rut' => $legalRep['rut'],
            'is_legal_rep' => true,
        ]));

        // A stable demo supervisor: an employee who also carries the
        // `supervisor` role, so they can review and approve/reject the leaves of
        // their own team. The team is wired up via `supervisor_id` below.
        $supervisor = User::factory()->employee()->create([
            'name' => 'Supervisor Demo',
            'first_name' => 'Supervisor',
            'last_name' => 'Demo',
            'email' => 'supervisor@example.com',
            'password' => 'admin',
            'organization_id' => $organization->id,
            'company_id' => $company->id,
            'premise_id' => $premises->first()->id,
            'rut' => '24805654-1',
            'vacation_days' => 15,
            'additional_vacation_days' => 2,
        ]);
        $supervisor->assignRole('supervisor');

        // A stable demo employee for logging in and exercising the employee
        // self-service flow (shares the single demo password). Reports to the
        // demo supervisor so their requests land in the supervisor's queue.
        User::factory()->employee()->create([
            'name' => 'Empleado Demo',
            'first_name' => 'Empleado',
            'last_name' => 'Demo',
            'email' => 'employee@example.com',
            'password' => 'admin',
            'organization_id' => $organization->id,
            'company_id' => $company->id,
            'premise_id' => $premises->first()->id,
            'supervisor_id' => $supervisor->id,
            'rut' => '21437581-8',
            'vacation_days' => 15,
            'additional_vacation_days' => 2,
        ]);

        // Real, verifier-valid Chilean RUTs for the demo employees.
        $ruts = [
            '23415645-4', '22922483-2', '22901821-3', '22200751-8', '5564530-2',
            '6528495-2', '5241573-k', '6279151-9', '23189180-3', '19504854-1',
            '22519021-6', '24869917-5', '21437581-8', '24805654-1', '9801359-8',
            '10749051-5', '7364409-7', '14122478-6', '14825683-7', '8022394-3',
        ];

        // A handful of employees so the Employees list is populated for demos,
        // split evenly across the two branches, each with a valid RUT. The first
        // half report to the demo supervisor so their team-leaves queue has a
        // spread of pending/approved/rejected requests to review.
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
                'supervisor_id' => $index < 6 ? $supervisor->id : null,
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
