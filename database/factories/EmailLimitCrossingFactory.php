<?php

namespace Database\Factories;

use App\Models\EmailLimitCrossing;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<EmailLimitCrossing>
 */
class EmailLimitCrossingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => EmailLimitCrossing::TYPE_HARD,
            'month' => Carbon::now()->startOfMonth(),
        ];
    }
}
