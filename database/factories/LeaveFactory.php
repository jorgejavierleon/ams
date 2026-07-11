<?php

namespace Database\Factories;

use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subDays(fake()->numberBetween(1, 10));

        return [
            'organization_id' => Organization::factory(),
            'company_id' => null,
            'user_id' => User::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(fake()->numberBetween(0, 4))->toDateString(),
            'half_day' => false,
            'half_day_type' => null,
            'business_days_requested' => fake()->numberBetween(1, 5),
            'status' => LeaveStatus::Pending,
            'type' => LeaveType::Vacation,
            'medical_leave_number' => null,
            'medical_leave_doctor' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * A pending leave awaiting a decision.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => LeaveStatus::Pending]);
    }

    /**
     * An already-approved leave.
     */
    public function approved(): static
    {
        return $this->state(fn () => ['status' => LeaveStatus::Approved]);
    }

    /**
     * A half-day (morning) leave on a single day.
     */
    public function halfDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'half_day' => true,
            'half_day_type' => LeaveHalfDayType::Morning,
            'business_days_requested' => 0.5,
            'end_date' => $attributes['start_date'],
        ]);
    }

    /**
     * A medical leave (auto-approved by the observer).
     */
    public function medical(): static
    {
        return $this->state(fn () => [
            'type' => LeaveType::Medical,
            'medical_leave_number' => (string) fake()->numberBetween(1000, 9999),
            'medical_leave_doctor' => fake()->name(),
        ]);
    }
}
