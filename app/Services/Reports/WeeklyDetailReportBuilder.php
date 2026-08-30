<?php

namespace App\Services\Reports;

use App\Enums\MarkModificationStatus;
use App\Models\MarkModification;
use App\Models\ShiftDay;
use App\Models\User;
use App\Models\Workday;
use App\Services\WorkdayCalculator;
use App\Support\CurrentOrganization;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the "Detalle Semanal por Trabajador" report (RF-1, KOL-21) —
 * Talana's "Reporte Semanal Persona" equivalent: one worker's days, real
 * versus theoretical entrada/salida/colación side by side, so a discrepancy
 * is visible without arithmetic.
 *
 * Individual level (unlike {@see PayrollSummaryReportBuilder}'s one row per
 * employee): this only ever renders when the shared selection (KOL-19)
 * resolves to exactly one employee, so it takes the same `list<int> $userIds`
 * every RF-1 report takes and treats anything but a single id as "nothing to
 * show" — the controller is what turns that into a prompt to narrow the
 * selection.
 *
 * Entrada, salida and their differences are read straight off `workdays`
 * (`mark_in_at`/`mark_out_at`, `shift_start_time`/`shift_end_time`,
 * `in_time_difference`/`out_time_difference`) — already computed by
 * {@see WorkdayCalculator}, never recalculated here. Colación
 * has no real counterpart: the system captures no lunch marks, mirroring
 * {@see DailyReportService}'s "No aplica" (Resolución 38, Art. 27 b.6) — only
 * the theoretical window is shown, resolved from the day's `ShiftDay` the
 * same way {@see WorkdayCalculator} does (shift_id + ISO
 * weekday, non-free day).
 */
class WeeklyDetailReportBuilder
{
    /**
     * @param  list<int>  $userIds
     * @return array{employee: array{id: int, name: string, rut: string|null}|null, weeks: list<array{start: string, end: string, days: list<array<string, mixed>>}>}
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        if (count($userIds) !== 1) {
            return ['employee' => null, 'weeks' => []];
        }

        $employee = User::query()
            ->where('organization_id', CurrentOrganization::id())
            ->find($userIds[0], ['id', 'name', 'rut']);

        if ($employee === null) {
            return ['employee' => null, 'weeks' => []];
        }

        $periodStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $periodEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $workdaysByDate = Workday::query()
            ->where('user_id', $employee->id)
            ->betweenDates($periodStart, $periodEnd)
            ->with(['leave', 'markModifications'])
            ->get()
            ->keyBy(fn (Workday $workday): string => $workday->date->format('Y-m-d'));

        $shiftDaysByShiftAndWeekday = $this->shiftDaysByShiftAndWeekday($workdaysByDate);

        $dates = [];

        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $date) {
            $dates[] = $date;
        }

        $weeks = array_values(
            collect($dates)
                ->groupBy(fn (Carbon $date): string => $date->format('o-W'))
                ->map(fn (Collection $weekDates): array => [
                    'start' => $weekDates->first()->format('Y-m-d'),
                    'end' => $weekDates->last()->format('Y-m-d'),
                    'days' => array_values($weekDates
                        ->map(fn (Carbon $date): array => $this->day(
                            $date,
                            $workdaysByDate->get($date->format('Y-m-d')),
                            $shiftDaysByShiftAndWeekday,
                        ))
                        ->all()),
                ])
                ->all()
        );

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'rut' => $employee->formatted_rut,
            ],
            'weeks' => $weeks,
        ];
    }

    /**
     * @param  array<int, array<int, ShiftDay>>  $shiftDaysByShiftAndWeekday
     * @return array<string, mixed>
     */
    private function day(Carbon $date, ?Workday $workday, array $shiftDaysByShiftAndWeekday): array
    {
        $weekday = $date->dayOfWeekIso - 1;
        $shiftDay = $workday?->shift_id === null
            ? null
            : ($shiftDaysByShiftAndWeekday[$workday->shift_id][$weekday] ?? null);

        return [
            'date' => $date->format('Y-m-d'),
            'weekday_label' => $date->isoFormat('dddd'),
            'date_label' => $date->isoFormat('D [de] MMMM'),
            'has_record' => $workday !== null,
            'status' => $workday?->status?->value,
            'status_label' => $workday?->status?->label(),
            'status_badge' => $workday?->status?->badge(),
            'entry' => [
                'real' => $workday?->mark_in_at?->format('H:i:s'),
                'theoretical' => $this->timeOfDay($workday?->shift_start_time),
                'difference' => $this->signedDifference($workday?->in_time_difference),
            ],
            'exit' => [
                'real' => $workday?->mark_out_at?->format('H:i:s'),
                'theoretical' => $this->timeOfDay($workday?->shift_end_time),
                'difference' => $this->signedDifference($workday?->out_time_difference),
            ],
            'lunch' => [
                // Not applicable: the system captures no colación marks
                // (Resolución 38, Art. 27 b.6), mirroring the DT daily report.
                'real' => null,
                'theoretical_start' => $shiftDay?->lunch_start_time?->format('H:i'),
                'theoretical_end' => $shiftDay?->lunch_end_time?->format('H:i'),
            ],
            'leave' => $workday?->leave === null ? null : [
                'type_label' => $workday->leave->type->label(),
            ],
            'has_pending_modification' => $workday?->markModifications
                ->contains(fn (MarkModification $modification): bool => $modification->isPending()) ?? false,
            'has_approved_modification' => $workday?->markModifications
                ->contains(fn (MarkModification $modification): bool => $modification->status === MarkModificationStatus::Approved) ?? false,
        ];
    }

    /**
     * Every `ShiftDay` (non-free) referenced by the period's workdays,
     * indexed by shift id then ISO weekday (0=Monday…6=Sunday) — the same
     * join key {@see WorkdayCalculator} uses — so each day's
     * theoretical colación window is a single lookup rather than a query.
     *
     * @param  Collection<string, Workday>  $workdaysByDate
     * @return array<int, array<int, ShiftDay>>
     */
    private function shiftDaysByShiftAndWeekday(Collection $workdaysByDate): array
    {
        $shiftIds = $workdaysByDate->pluck('shift_id')->filter()->unique()->values()->all();

        if ($shiftIds === []) {
            return [];
        }

        return ShiftDay::query()
            ->whereIn('shift_id', $shiftIds)
            ->where('is_free', false)
            ->get(['shift_id', 'weekday', 'lunch_start_time', 'lunch_end_time'])
            ->groupBy('shift_id')
            ->map(fn (Collection $days): array => $days->keyBy('weekday')->all())
            ->all();
    }

    /**
     * Normalise a stored `TIME` value to `HH:MM`, or null when unset.
     */
    private function timeOfDay(?string $time): ?string
    {
        return $time === null ? null : Carbon::parse($time)->format('H:i');
    }

    /**
     * Prefix a stored signed `TIME` difference with "+" when positive, so a
     * late entrada and an early salida read the same way at a glance. Zero
     * and negative values (MySQL's `TIMEDIFF` already carries a leading "-")
     * are left as stored.
     */
    private function signedDifference(?string $time): ?string
    {
        if ($time === null || $time === '00:00:00' || str_starts_with($time, '-')) {
            return $time;
        }

        return '+'.$time;
    }
}
