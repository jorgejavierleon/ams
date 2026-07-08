<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'notification_date' => now()->subWeek()->toDateString(),
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => null,
            'is_permanent' => true,
            'requested_by_employee' => false,
        ];
    }

    /**
     * An assignment that already ended (no longer active).
     */
    public function ended(): static
    {
        return $this->state(fn () => [
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'is_permanent' => false,
        ]);
    }
}
