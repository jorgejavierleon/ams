<?php

namespace Database\Factories;

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarkModification>
 */
class MarkModificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workday_id' => Workday::factory(),
            'user_id' => User::factory(),
            'reason' => MarkModificationReason::MarkForgotten,
            'status' => MarkModificationStatus::Pending,
            'date_time' => now()->setTime(8, 0),
            'mark_type' => MarkType::In,
            'notes' => fake()->sentence(),
        ];
    }

    /**
     * A modification that has already been reviewed and approved.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => MarkModificationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
