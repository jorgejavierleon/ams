<?php

namespace Database\Factories;

use App\Enums\OvertimePactStatus;
use App\Models\Organization;
use App\Models\OvertimePact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimePact>
 */
class OvertimePactFactory extends Factory
{
    /**
     * A one-month agreement starting today, well inside the three-month cap.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => OvertimePactStatus::Active,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OvertimePactStatus::Revoked]);
    }
}
