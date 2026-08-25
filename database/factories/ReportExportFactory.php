<?php

namespace Database\Factories;

use App\Enums\ReportExportStatus;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'type' => 'attendance',
            'format' => 'excel',
            'filters' => [
                'start' => '2026-03-01',
                'end' => '2026-03-31',
                'user_ids' => [],
            ],
            'status' => ReportExportStatus::Pending,
        ];
    }

    /**
     * A finished export, downloadable until it expires.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportExportStatus::Ready,
            'disk_path' => 'report-exports/test.xlsx',
            'filename' => 'test.xlsx',
            'expires_at' => now()->addMinutes(config('reports.export.link_expiry_minutes')),
        ]);
    }

    /**
     * An export whose signed link has already lapsed.
     */
    public function expired(): static
    {
        return $this->ready()->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * A failed export.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportExportStatus::Failed,
            'failure_reason' => 'Something went wrong.',
        ]);
    }
}
