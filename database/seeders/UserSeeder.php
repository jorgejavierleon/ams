<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'admin',
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
