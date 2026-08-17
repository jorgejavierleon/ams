<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OvertimeRestDayBalance;
use App\Models\OvertimeRestDayConsumption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OvertimeRestDayConsumption>
 */
class OvertimeRestDayConsumptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'overtime_rest_day_balance_id' => OvertimeRestDayBalance::factory(),
            'hours' => '01:00:00',
            'consumed_on' => Carbon::today(),
            'note' => null,
            'registered_by' => null,
        ];
    }
}
