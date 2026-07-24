<?php

namespace Database\Factories;

use App\Models\DocumentVar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVar>
 */
class DocumentVarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = implode(' ', (array) $this->faker->unique()->words(2));

        return [
            'name' => ucfirst($key),
            'key' => '{{'.str_replace(' ', '_', $key).'}}',
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
