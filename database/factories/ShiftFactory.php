<?php

namespace Database\Factories;

use App\Enums\ShiftType;
use App\Models\Organization;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => ShiftType::Fixed,
            'name' => 'Turno '.fake()->unique()->word(),
            'description' => fake()->optional()->sentence(),
            'tolerance_in' => '00:10',
            'tolerance_out' => '00:10',
            'work_on_holidays' => false,
            'is_archive' => false,
            'is_default' => false,
        ];
    }

    /**
     * Mark this shift as the organization's default.
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
