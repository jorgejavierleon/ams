<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => "Región de {$name}",
            'short_name' => $name,
            'initials' => strtoupper(fake()->unique()->bothify('??#')),
            'order' => fake()->unique()->numberBetween(1, 16),
        ];
    }
}
