<?php

namespace Database\Factories;

use App\Enums\OvertimeAuthorizationStatus;
use App\Models\Organization;
use App\Models\OvertimeAuthorization;
use App\Models\User;
use App\Models\Workday;
use App\Support\Duration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeAuthorization>
 */
class OvertimeAuthorizationFactory extends Factory
{
    /**
     * A day the engine calculated two hours of overtime for, awaiting a
     * decision. Pending is the only state a record can be born in.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'workday_id' => Workday::factory(),
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'calculated_hours' => '02:00:00',
            'requested_hours' => null,
            'authorized_hours' => null,
            'final_hours' => null,
            'status' => OvertimeAuthorizationStatus::Pending,
        ];
    }

    /**
     * A day authorised in full: everything the engine calculated is payable.
     *
     * The reviewer is required by the model, so this state supplies one rather
     * than leaving the row to be refused on save.
     */
    public function approved(?User $reviewer = null, ?string $authorizedHours = null): static
    {
        return $this->state(function (array $attributes) use ($reviewer, $authorizedHours): array {
            $hours = $authorizedHours ?? $attributes['calculated_hours'];

            return [
                'status' => OvertimeAuthorizationStatus::Approved,
                'authorized_hours' => $hours,
                'final_hours' => (string) (Duration::min(
                    Duration::tryFrom($hours),
                    Duration::tryFrom($attributes['calculated_hours']),
                ) ?? Duration::zero()),
                'reviewed_by' => $reviewer->id ?? User::factory()->state([
                    'organization_id' => $attributes['organization_id'],
                ]),
                'reviewed_at' => now(),
            ];
        });
    }

    /**
     * A day whose overtime a supervisor refused. Nothing is payable; the worked
     * hours stay readable as unauthorised.
     */
    public function objected(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OvertimeAuthorizationStatus::Objected,
            'authorized_hours' => '00:00:00',
            'final_hours' => '00:00:00',
            'reason' => 'Horas no autorizadas por la jefatura.',
            'reviewed_by' => $reviewer->id ?? User::factory()->state([
                'organization_id' => $attributes['organization_id'],
            ]),
            'reviewed_at' => now(),
        ]);
    }
}
