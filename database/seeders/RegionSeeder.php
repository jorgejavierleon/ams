<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class RegionSeeder extends Seeder
{
    /**
     * Seed the Chilean regions and communes reference data.
     */
    public function run(): void
    {
        Artisan::call('app:seed-chilean-regions');
    }
}
