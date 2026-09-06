<?php

namespace Database\Factories;

use App\Enums\ImportIssueSeverity;
use App\Models\ImportRun;
use App\Models\ImportRunIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRunIssue>
 */
class ImportRunIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'row_number' => $this->faker->numberBetween(2, 500),
            'field' => $this->faker->randomElement(['rut', 'email', null]),
            'severity' => ImportIssueSeverity::Error,
            'message' => $this->faker->sentence(),
        ];
    }
}
