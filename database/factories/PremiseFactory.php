<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Organization;
use App\Models\Premise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Premise>
 */
class PremiseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'company_id' => null,
            'name' => fake()->company().' - '.fake()->city(),
            'code' => fake()->optional()->bothify('SUC-###'),
            'country' => 'Chile',
            'region' => fake()->optional()->randomElement([
                'Arica y Parinacota', 'Tarapacá', 'Antofagasta', 'Atacama', 'Coquimbo',
                'Valparaíso', 'Metropolitana de Santiago', "Libertador General Bernardo O'Higgins",
                'Maule', 'Ñuble', 'Biobío', 'La Araucanía', 'Los Ríos', 'Los Lagos',
                'Aysén del General Carlos Ibáñez del Campo', 'Magallanes y de la Antártica Chilena',
            ]),
            'commune' => fake()->optional()->city(),
            'address' => fake()->streetAddress(),
            // Chilean bounding box, kept within the stored column precision.
            'lat' => fake()->randomFloat(6, -55, -17),
            'lng' => fake()->randomFloat(6, -75, -66),
            'responsable_name' => fake()->optional()->name(),
            'responsable_email' => fake()->optional()->safeEmail(),
            'responsable_phone' => fake()->optional()->numerify('+569########'),
        ];
    }

    /**
     * Attach the premise to a company (in the same organization).
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn () => [
            'organization_id' => $company->organization_id,
            'company_id' => $company->id,
        ]);
    }

    /**
     * Premise with no geolocation set.
     */
    public function withoutCoordinates(): static
    {
        return $this->state(fn () => [
            'lat' => null,
            'lng' => null,
        ]);
    }
}
