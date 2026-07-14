<?php

namespace Database\Factories;

use App\Enums\WorkdayStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workday>
 */
class WorkdayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'shift_start_time' => '08:00:00',
            'shift_end_time' => '17:00:00',
            'mark_in_at' => now()->setTime(8, 2),
            'mark_out_at' => now()->setTime(17, 5),
            'in_time_difference' => '00:02:00',
            'out_time_difference' => '00:05:00',
            'worked_time' => '08:03:00',
            'extra_time' => '00:00:00',
            'missing_time' => '00:00:00',
            'status' => WorkdayStatus::Regular,
        ];
    }

    /**
     * A day the employee failed to show up for a scheduled shift.
     */
    public function absent(): static
    {
        return $this->state(fn (): array => [
            'mark_in_at' => null,
            'mark_out_at' => null,
            'mark_in_id' => null,
            'mark_out_id' => null,
            'in_time_difference' => null,
            'out_time_difference' => null,
            'worked_time' => '00:00:00',
            'status' => WorkdayStatus::Absent,
        ]);
    }

    /**
     * A day with only one of the two marks registered.
     */
    public function incomplete(): static
    {
        return $this->state(fn (): array => [
            'mark_out_at' => null,
            'mark_out_id' => null,
            'out_time_difference' => null,
            'worked_time' => '00:00:00',
            'status' => WorkdayStatus::Incomplete,
        ]);
    }
}
