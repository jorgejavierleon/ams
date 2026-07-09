<?php

namespace Database\Factories;

use App\Models\Holiday;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state: an official (global) holiday.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'country' => 'cl',
            'name' => fake()->sentence(2),
            'date' => fake()->unique()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
            'mandatory' => fake()->boolean(),
        ];
    }

    /**
     * A holiday owned by (and editable only by) a single organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(['organization_id' => $organization->id]);
    }
}
