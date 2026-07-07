<?php

namespace Database\Seeders;

use App\Models\Organization;
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
