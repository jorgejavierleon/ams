<?php

namespace Database\Factories;

use App\Models\LegalHourLimit;
use App\Services\LegalHourLimitVersions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<LegalHourLimit>
 */
class LegalHourLimitFactory extends Factory
{
    protected $model = LegalHourLimit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'effective_from' => fake()->dateTimeBetween('+1 year', '+5 years')->format('Y-m-d'),
            'ordinary_weekly_hours' => 40,
            'ordinary_daily_hours' => 10,
            'max_overtime_daily_hours' => 2,
            'max_overtime_weekly_hours' => 12,
            'max_total_daily_hours' => 12,
            'max_total_weekly_hours' => 52,
            'legal_reference' => 'Ley '.fake()->numberBetween(21000, 21999),
            'notes' => null,
        ];
    }

    /**
     * The model refuses any create that does not come through the append path,
     * so the factory unlocks it the same way {@see LegalHourLimitVersions::add()}
     * does rather than reaching around the guard.
     *
     * @param  array<string, mixed>|callable  $attributes
     * @return Collection<int, LegalHourLimit>|LegalHourLimit
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        return LegalHourLimit::whileAppending(fn () => parent::create($attributes, $parent));
    }

    public function ordinaryWeeklyHours(float $hours): static
    {
        return $this->state(fn (): array => [
            'ordinary_weekly_hours' => $hours,
            'max_total_weekly_hours' => $hours + 12,
        ]);
    }

    public function effectiveFrom(string $date): static
    {
        return $this->state(fn (): array => ['effective_from' => $date]);
    }
}
