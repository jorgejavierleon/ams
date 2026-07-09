<?php

namespace Database\Seeders;

use App\Enums\ShiftType;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ShiftSeeder extends Seeder
{
    /**
     * Seed a couple of demo shifts (each with its weekly schedule) for the demo
     * organization and give every demo employee a permanent assignment, so the
     * Shifts list and every employee's shift-assignment tab have data to test.
     *
     * DatabaseSeeder runs with WithoutModelEvents, so the ShiftObserver and
     * ShiftDayObserver do not fire here — the days and the derived hour totals
     * are therefore written explicitly rather than left to the observers.
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('slug', 'demo-organization')
            ->first();

        if ($organization === null) {
            return;
        }

        $shifts = [
            $this->createShift($organization, [
                'name' => 'Turno Mañana',
                'description' => 'Lunes a viernes, 08:00 a 17:00 con una hora de colación.',
                'is_default' => true,
            ], start: '08:00:00', end: '17:00:00'),
            $this->createShift($organization, [
                'name' => 'Turno Tarde',
                'description' => 'Lunes a viernes, 14:00 a 22:00 con una hora de colación.',
                'is_default' => false,
            ], start: '14:00:00', end: '22:00:00', lunchStart: '18:00:00', lunchEnd: '19:00:00'),
        ];

        // One permanent assignment per employee, alternating between the shifts
        // so both are exercised across the list.
        User::query()
            ->employees()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get()
            ->each(function (User $employee, int $index) use ($organization, $shifts): void {
                ShiftAssignment::query()->create([
                    'organization_id' => $organization->id,
                    'shift_id' => $shifts[$index % count($shifts)]->id,
                    'user_id' => $employee->id,
                    'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
                    'end_date' => null,
                    'is_permanent' => true,
                ]);
            });
    }

    /**
     * Create a shift with its seven day rows and derived hour totals.
     *
     * @param  array{name: string, description: string, is_default: bool}  $attributes
     */
    private function createShift(
        Organization $organization,
        array $attributes,
        string $start,
        string $end,
        string $lunchStart = '12:00:00',
        string $lunchEnd = '13:00:00',
    ): Shift {
        $shift = new Shift([
            'type' => ShiftType::Fixed,
            'name' => $attributes['name'],
            'description' => $attributes['description'],
            'tolerance_in' => '00:10:00',
            'tolerance_out' => '00:10:00',
            'work_on_holidays' => false,
            'is_archive' => false,
            'is_default' => $attributes['is_default'],
        ]);
        $shift->organization_id = $organization->id;
        $shift->save();

        $this->seedWeeklySchedule($shift, $start, $end, $lunchStart, $lunchEnd);

        // total_week_hours is a derived, non-fillable column normally rolled up
        // by the ShiftDayObserver; set it directly since events are muted.
        $shift->total_week_hours = $shift->days()->sum('total_work_hours');
        $shift->save();

        return $shift;
    }

    /**
     * Create the seven day rows: Mon–Fri working the given hours with the given
     * lunch break, Saturday and Sunday non-working. total_work_hours is a
     * non-fillable column derived by ShiftDay's saving hook, which is muted
     * during seeding, so it is computed and assigned directly here.
     */
    private function seedWeeklySchedule(Shift $shift, string $start, string $end, string $lunchStart, string $lunchEnd): void
    {
        $workedHours = (
            Carbon::parse($start)->diffInMinutes(Carbon::parse($end))
            - Carbon::parse($lunchStart)->diffInMinutes(Carbon::parse($lunchEnd))
        ) / 60;

        for ($weekday = 0; $weekday < 7; $weekday++) {
            $isFree = $weekday === 5 || $weekday === 6;

            $day = new ShiftDay([
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'lunch_start_time' => $lunchStart,
                'lunch_end_time' => $lunchEnd,
                'is_free' => $isFree,
            ]);
            $day->shift_id = $shift->id;
            $day->total_work_hours = $isFree ? 0 : $workedHours;
            $day->save();
        }
    }
}
