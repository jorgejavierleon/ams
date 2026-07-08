<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\ShiftDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftDay>
 */
class ShiftDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'weekday' => 0,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'lunch_start_time' => '12:00:00',
            'lunch_end_time' => '13:00:00',
            'is_free' => false,
        ];
    }

    /**
     * A non-working (free) day.
     */
    public function free(): static
    {
        return $this->state(fn () => ['is_free' => true]);
    }
}
