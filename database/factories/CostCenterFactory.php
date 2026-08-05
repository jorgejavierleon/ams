<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->numerify('CC-###'),
        ];
    }

    /**
     * A cost centre the client never matched to an accounting catalogue.
     */
    public function withoutCode(): static
    {
        return $this->state(fn (array $attributes) => ['code' => null]);
    }
}
