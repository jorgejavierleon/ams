<?php

namespace App\Services\Reports;

use App\Enums\ShiftType;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the "Reporte de modificaciones y/o alteraciones de turnos" required by
 * Resolución 38, Art. 27 d): for each selected worker, every shift change that
 * took effect (or ended) within the chosen range, laid out as the article's
 * nine columns — the previous shift (start date, detail, extension), the new
 * shift's notification date, start date, detail and extension, who requested
 * the change and a free observation.
 *
 * A change is a shift assignment whose start_date or end_date falls in the
 * range. Rows are ordered by start date and each row's "previous shift"
 * (d.2–d.4) is the assignment immediately before it in that ordered set, so the
 * first row carries no previous shift, exactly as the DT-authorized legacy
 * report produced it.
 *
 * When a worker has no change in the range the block carries an `emptyReason`
 * instead of rows: `fixed-journey` for workers on a permanent fixed journey
 * (Art. 27 d), whose lack of information the report must justify, or
 * `no-changes` — "Sin cambios o modificaciones en el periodo consultado".
 */
class ShiftChangesReportService
{
    /**
     * @param  list<int>  $userIds
     * @return list<array{
     *     employee: string,
     *     employer: string|null,
     *     premise: string|null,
     *     rows: list<array<string, mixed>>,
     *     emptyReason: 'fixed-journey'|'no-changes'|null,
     * }>
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->with(['company:id,social_reason,rut', 'premise:id,name'])
            ->orderBy('name')
            ->get();

        $assignments = $this->shiftAssignmentsByUser($userIds);

        return array_values($users->map(function (User $user) use ($start, $end, $assignments): array {
            $userAssignments = $assignments->get($user->id, collect());
            $rows = $this->rows($userAssignments, $start, $end);

            return [
                'employee' => $this->label($user->name, $user->formatted_rut ?? $user->rut),
                'employer' => $user->company === null
                    ? null
                    : $this->label($user->company->social_reason, $user->company->formatted_rut ?? $user->company->rut),
                'premise' => $user->premise?->name,
                'rows' => $rows,
                'emptyReason' => $rows === [] ? $this->emptyReason($userAssignments) : null,
            ];
        })->all());
    }

    /**
     * Build the change rows: the assignments taking effect or ending within the
     * range, ordered by start date, each paired with the one before it as its
     * previous shift (the first row has none).
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $assignments, Carbon $start, Carbon $end): array
    {
        $changes = $assignments
            ->filter(fn (ShiftAssignment $assignment): bool => $assignment->start_date->betweenIncluded($start, $end)
                || ($assignment->end_date !== null && $assignment->end_date->betweenIncluded($start, $end)))
            ->sortBy('start_date')
            ->values();

        return array_values($changes
            ->map(function (ShiftAssignment $assignment, int $index) use ($changes): array {
                $previous = $index === 0 ? null : $changes->get($index - 1);

                return [
                    // d.2–d.4: the previous shift, absent on the first row.
                    'oldStartDate' => $previous === null ? null : $this->date($previous->start_date),
                    'oldShift' => $previous === null ? null : $this->shiftDetail($previous),
                    'oldExtension' => $previous === null ? null : $this->extension($previous),
                    // d.5–d.8: the new shift.
                    'notificationDate' => $this->date($assignment->notification_date),
                    'newStartDate' => $this->date($assignment->start_date),
                    'newShift' => $this->shiftDetail($assignment),
                    'newExtension' => $this->extension($assignment),
                    // d.9: only "Trabajador" or "Empleador".
                    'requestedBy' => $assignment->requested_by_employee ? 'employee' : 'employer',
                    // d.10: observations — none captured by the domain.
                    'observation' => null,
                ];
            })
            ->all());
    }

    /**
     * Why a worker's block has no rows: a permanent fixed journey (whose absence
     * of shift changes the article requires justifying) or simply no change in
     * the range.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return 'fixed-journey'|'no-changes'
     */
    private function emptyReason(Collection $assignments): string
    {
        $current = $assignments->sortByDesc('start_date')->first();

        return $current?->shift?->type === ShiftType::Fixed ? 'fixed-journey' : 'no-changes';
    }

    /**
     * The shift detail column (d.3, d.7): the shift's description, falling back
     * to its name.
     */
    private function shiftDetail(ShiftAssignment $assignment): string
    {
        return $assignment->shift->description ?? $assignment->shift->name ?? '';
    }

    /**
     * The shift extension column (d.4, d.8): the shift type, translated on the
     * client ("Fijo", "Rotativo", "Quincenal", …).
     */
    private function extension(ShiftAssignment $assignment): string
    {
        return $assignment->shift?->type->value ?? '';
    }

    /**
     * Format a date as dd/mm/aa per Art. 27 d, or null when absent.
     */
    private function date(?CarbonInterface $date): ?string
    {
        return $date?->format('d/m/y');
    }

    /**
     * Every shift assignment of the given workers, with its shift, so the change
     * rows and the fixed-journey check can read shift type and detail.
     *
     * @param  list<int>  $userIds
     * @return Collection<int|string, EloquentCollection<int, ShiftAssignment>>
     */
    private function shiftAssignmentsByUser(array $userIds): Collection
    {
        return ShiftAssignment::query()
            ->whereIn('user_id', $userIds)
            ->with('shift:id,type,name,description')
            ->get()
            ->groupBy('user_id');
    }

    /**
     * Join a name and RUT as "Name - 12.345.678-5", dropping a missing RUT.
     */
    private function label(string $name, ?string $rut): string
    {
        return $rut === null ? $name : "{$name} - {$rut}";
    }
}
