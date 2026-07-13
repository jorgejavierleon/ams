<?php

namespace Database\Factories;

use App\Enums\MarkType;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mark>
 */
class MarkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'type' => MarkType::In,
            'date_time' => now(),
        ];
    }

    /**
     * A punch registering the employee leaving work.
     */
    public function out(): static
    {
        return $this->state(fn () => ['type' => MarkType::Out]);
    }
}
