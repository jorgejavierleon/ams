<?php

namespace Database\Factories;

use App\Enums\OvertimeRequestStatus;
use App\Models\Organization;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeRequest>
 */
class OvertimeRequestFactory extends Factory
{
    /**
     * A pending ask for two hours of overtime today. Pending is the only
     * state a request can be born in.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'requested_hours' => '02:00:00',
            'reason' => null,
            'status' => OvertimeRequestStatus::Pending,
        ];
    }

    /**
     * A request a supervisor approved. The reviewer is required by the model,
     * so this state supplies one rather than leaving the row to be refused on
     * save.
     */
    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OvertimeRequestStatus::Approved,
            'reviewed_by' => $reviewer->id ?? User::factory()->state([
                'organization_id' => $attributes['organization_id'],
            ]),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * A request a supervisor rejected.
     */
    public function rejected(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OvertimeRequestStatus::Rejected,
            'decision_reason' => 'No se autoriza la solicitud.',
            'reviewed_by' => $reviewer->id ?? User::factory()->state([
                'organization_id' => $attributes['organization_id'],
            ]),
            'reviewed_at' => now(),
        ]);
    }
}
