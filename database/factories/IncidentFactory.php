<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeThisYear();
        $endTime = (clone $startTime)->modify('+'.fake()->numberBetween(1, 60).' minutes');

        return [
            'organization_id' => Organization::factory(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'description' => fake()->sentence(),
        ];
    }
}
