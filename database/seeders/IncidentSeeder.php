<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class IncidentSeeder extends Seeder
{
    /**
     * Seed a spread of technical incidents for the demo employer so the DT
     * incidents list, its date-range filter and the ongoing/duration display
     * all have data to exercise.
     *
     * DatabaseSeeder runs with WithoutModelEvents, so the BelongsToOrganization
     * creating hook does not fire here — organization_id is written explicitly.
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('slug', 'demo-organization')
            ->first();

        if ($organization === null) {
            return;
        }

        // A handful of resolved outages across recent months, oldest first, so
        // the date filter has rows on either side of any chosen window.
        $outages = [
            ['start' => Carbon::now()->subMonths(3)->setTime(9, 15), 'minutes' => 25],
            ['start' => Carbon::now()->subMonths(2)->setTime(14, 0), 'minutes' => 90],
            ['start' => Carbon::now()->subMonth()->setTime(8, 30), 'minutes' => 12],
            ['start' => Carbon::now()->subWeeks(2)->setTime(11, 45), 'minutes' => 180],
        ];

        foreach ($outages as $outage) {
            Incident::factory()->create([
                'organization_id' => $organization->id,
                'start_time' => $outage['start'],
                'end_time' => $outage['start']->copy()->addMinutes($outage['minutes']),
            ]);
        }

        // One still-open outage (no end time) so the "ongoing" state is visible.
        Incident::factory()->create([
            'organization_id' => $organization->id,
            'start_time' => Carbon::now()->subDays(2)->setTime(16, 20),
            'end_time' => null,
        ]);
    }
}
