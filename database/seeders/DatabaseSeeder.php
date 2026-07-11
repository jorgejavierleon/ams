<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ShiftSeeder::class,
            HolidaySeeder::class,
            LeaveSeeder::class,
            OrganizationSeeder::class,
            RegionSeeder::class,
        ]);
    }
}
