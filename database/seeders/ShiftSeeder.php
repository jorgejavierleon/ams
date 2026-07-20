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
     * Seed a few demo shifts (each with its weekly schedule) for the demo
     * organization and give every demo employee at least one assignment, so the
     * Shifts list and every employee's shift-assignment tab have data to test.
     *
     * The first employees are given a history of shift changes — including a
     * transition landing in the current month — so the DT shift-changes report
     * (Resolución 38, Art. 27 d) has real modifications to display, alongside
     * workers whose blocks exercise its "no changes" and fixed-journey legends.
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

        $morning = $this->createShift($organization, [
            'name' => 'Turno Mañana',
            'description' => 'Lunes a viernes, 08:00 a 17:00 con una hora de colación.',
            'type' => ShiftType::Fixed,
            'is_default' => true,
        ], start: '08:00:00', end: '17:00:00');
        $afternoon = $this->createShift($organization, [
            'name' => 'Turno Tarde',
            'description' => 'Lunes a viernes, 14:00 a 22:00 con una hora de colación.',
            'type' => ShiftType::Fixed,
            'is_default' => false,
        ], start: '14:00:00', end: '22:00:00', lunchStart: '18:00:00', lunchEnd: '19:00:00');
        $rotating = $this->createShift($organization, [
            'name' => 'Turno Rotativo',
            'description' => 'Lunes a viernes, 06:00 a 15:00 con una hora de colación.',
            'type' => ShiftType::Rotational,
            'is_default' => false,
        ], start: '06:00:00', end: '15:00:00', lunchStart: '11:00:00', lunchEnd: '12:00:00');

        // Three-shift histories exercising the shift-changes report: each ends
        // with a transition in the current month, so even the default
        // current-month range shows a previous → new shift change.
        $histories = [
            [$morning, $afternoon, $rotating],
            [$afternoon, $rotating, $morning],
            [$rotating, $morning, $afternoon],
        ];

        $employees = User::query()
            ->employees()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        $fallbackShifts = [$morning, $afternoon];

        $employees->each(function (User $employee, int $index) use ($organization, $histories, $rotating, $fallbackShifts): void {
            if (isset($histories[$index])) {
                $this->seedShiftChangeHistory($organization, $employee, $histories[$index]);

                return;
            }

            // One worker on a permanent rotational shift with no change in the
            // period: exercises the report's "Sin cambios" legend.
            if ($index === count($histories)) {
                $this->createAssignment($organization, $employee, $rotating, [
                    'start_date' => now()->subMonths(6)->startOfMonth(),
                    'end_date' => null,
                    'is_permanent' => true,
                ]);

                return;
            }

            // Everyone else: a permanent fixed assignment (the fixed-journey
            // legend when queried outside its start month).
            $this->createAssignment($organization, $employee, $fallbackShifts[$index % count($fallbackShifts)], [
                'start_date' => now()->subMonth()->startOfMonth(),
                'end_date' => null,
                'is_permanent' => true,
            ]);
        });
    }

    /**
     * Give an employee a three-step shift history: two closed assignments and a
     * current one, with the last change taking effect mid-current-month so it
     * falls in the report's default range.
     *
     * @param  array{0: Shift, 1: Shift, 2: Shift}  $shifts
     */
    private function seedShiftChangeHistory(Organization $organization, User $employee, array $shifts): void
    {
        $firstStart = now()->subMonths(2)->startOfMonth();
        $secondStart = now()->subMonth()->startOfMonth();
        $currentStart = now()->startOfMonth()->addDays(15);

        // First shift: from two months ago until the day before the second.
        $this->createAssignment($organization, $employee, $shifts[0], [
            'notification_date' => $firstStart->copy()->subDays(7),
            'start_date' => $firstStart,
            'end_date' => $secondStart->copy()->subDay(),
            'is_permanent' => false,
            'requested_by_employee' => false,
        ]);

        // Second shift: last month until the day before the current change.
        $this->createAssignment($organization, $employee, $shifts[1], [
            'notification_date' => $secondStart->copy()->subDays(5),
            'start_date' => $secondStart,
            'end_date' => $currentStart->copy()->subDay(),
            'is_permanent' => false,
            'requested_by_employee' => true,
        ]);

        // Current shift: starts mid-current-month, permanent.
        $this->createAssignment($organization, $employee, $shifts[2], [
            'notification_date' => $currentStart->copy()->subDays(3),
            'start_date' => $currentStart,
            'end_date' => null,
            'is_permanent' => true,
            'requested_by_employee' => false,
        ]);
    }

    /**
     * Persist one shift assignment for an employee, defaulting the organization
     * and dropping any Carbon dates to their date string.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createAssignment(Organization $organization, User $employee, Shift $shift, array $attributes): void
    {
        $attributes = array_map(
            fn ($value) => $value instanceof Carbon ? $value->toDateString() : $value,
            $attributes,
        );

        ShiftAssignment::query()->create([
            'organization_id' => $organization->id,
            'shift_id' => $shift->id,
            'user_id' => $employee->id,
            ...$attributes,
        ]);
    }

    /**
     * Create a shift with its seven day rows and derived hour totals.
     *
     * @param  array{name: string, description: string, type: ShiftType, is_default: bool}  $attributes
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
            'type' => $attributes['type'],
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
