<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRestDayBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OvertimeRestDayBalance>
 */
class OvertimeRestDayBalanceFactory extends Factory
{
    /**
     * A two-hour accrual (three rest-hours at the 1.5x statutory ratio),
     * fresh today with the full six-month runway still ahead of it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accrualDate = Carbon::today();

        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'overtime_authorization_id' => OvertimeAuthorization::factory(),
            'accrued_hours' => '02:00:00',
            'rest_hours' => '03:00:00',
            'consumed_hours' => '00:00:00',
            'accrual_date' => $accrualDate,
            'expiry_date' => $accrualDate->copy()->addMonths(6),
        ];
    }

    /**
     * Accrued far enough in the past that its expiry date has already
     * elapsed, without having been swept yet — the sweep's target population.
     */
    public function pastExpiry(): static
    {
        return $this->state(function (): array {
            $accrualDate = Carbon::today()->subMonths(7);

            return [
                'accrual_date' => $accrualDate,
                'expiry_date' => $accrualDate->copy()->addMonths(6),
            ];
        });
    }
}
