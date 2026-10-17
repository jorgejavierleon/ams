<?php

namespace Database\Factories;

use App\Models\Premise;
use App\Models\TotemDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TotemDevice>
 */
class TotemDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn (array $attributes) => Premise::withoutGlobalScopes()
                ->find($attributes['premise_id'])->organization_id,
            'premise_id' => Premise::factory(),
            'name' => 'Totem '.fake()->word(),
        ];
    }

    /**
     * Device for an existing premise, in that premise's organization.
     */
    public function forPremise(Premise $premise): static
    {
        return $this->state(fn () => [
            'organization_id' => $premise->organization_id,
            'premise_id' => $premise->id,
        ]);
    }
}
