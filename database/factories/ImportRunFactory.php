<?php

namespace Database\Factories;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'status' => ImportRunStatus::Pending,
        ];
    }
}
