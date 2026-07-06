<?php

namespace Database\Factories;

use App\Models\Commune;
use App\Models\Company;
use App\Models\Organization;
use App\Models\Region;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = (string) fake()->numberBetween(1_000_000, 25_000_000);

        return [
            'organization_id' => Organization::factory(),
            'rut' => $body.'-'.Rut::computeDv($body),
            'social_reason' => fake()->company(),
            'business_line' => fake()->words(3, true),
            'email' => fake()->unique()->companyEmail(),
            'country' => 'Chile',
            'region_id' => null,
            'commune_id' => null,
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('+569########'),
            'company_type' => fake()->randomElement(['SpA', 'Ltda.', 'S.A.', 'EIRL']),
            'is_est' => false,
            'is_active' => true,
        ];
    }

    /**
     * Attach a consistent region and one of its communes.
     */
    public function located(): static
    {
        return $this->state(function () {
            $region = Region::factory()->create();
            $commune = Commune::factory()->create(['region_id' => $region->id]);

            return [
                'region_id' => $region->id,
                'commune_id' => $commune->id,
            ];
        });
    }
}
