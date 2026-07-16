<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\Organization;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $rutBody = (string) fake()->unique()->numberBetween(1_000_000, 25_000_000);

        return [
            'name' => $name,
            'rut' => $rutBody.'-'.Rut::computeDv($rutBody),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('+569########'),
            'address' => fake()->streetAddress(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'plan' => fake()->randomElement(Plan::cases()),
        ];
    }
}
