<?php

namespace App\Services\Reports;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Builds "Movimientos del Período" (RF-1, KOL-22) — Talana's "Movimientos del
 * Mes" equivalent: everything besides hours worked that changes what an
 * employee is paid — altas, bajas, licencia starts and ends, approved
 * vacations and shift changes — grouped into the six movement types the
 * exporter turns into one sheet each via {@see ReportWriter::excelSheets()}.
 *
 * A licencia (or vacation) is "in the period" per its own edge, not as a
 * whole range: a start counts when the start date falls in the period, an
 * end counts when the end date falls in the period, independently of one
 * another. A leave that began before the period and ends inside it therefore
 * appears only among the ends; one that begins inside and runs past the
 * period appears only among the starts; one fully inside the period appears
 * in both (AC #4) — no special-casing needed, it falls out of filtering each
 * edge on its own column.
 *
 * Shift changes are not recomputed here: {@see ShiftChangesReportService}
 * (the DT Art. 27 d) report) already defines what a shift change is, and
 * this reuses it verbatim rather than inventing a second notion (AC #5).
 */
class PeriodMovementsReportBuilder
{
    public function __construct(private ShiftChangesReportService $shiftChangesService) {}

    /**
     * @param  list<int>  $userIds
     * @return array{
     *     hires: list<array{employee: string, rut: string|null, position: string|null, premise: string|null, date: string}>,
     *     terminations: list<array{employee: string, rut: string|null, position: string|null, premise: string|null, date: string}>,
     *     leaveStarts: list<array{employee: string, rut: string|null, type: string, startDate: string, endDate: string, days: float}>,
     *     leaveEnds: list<array{employee: string, rut: string|null, type: string, startDate: string, endDate: string, days: float}>,
     *     vacations: list<array{employee: string, rut: string|null, type: string, startDate: string, endDate: string, days: float}>,
     *     shiftChanges: list<array<string, mixed>>,
     * }
     */
    public function build(Carbon $start, Carbon $end, array $userIds): array
    {
        if ($userIds === []) {
            return [
                'hires' => [],
                'terminations' => [],
                'leaveStarts' => [],
                'leaveEnds' => [],
                'vacations' => [],
                'shiftChanges' => [],
            ];
        }

        $users = User::query()
            ->where('organization_id', CurrentOrganization::id())
            ->whereIn('id', $userIds)
            ->with(['position:id,name', 'premise:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'rut', 'position_id', 'premise_id', 'contract_start_date', 'contract_end_date']);

        return [
            'hires' => $this->hires($users, $start, $end),
            'terminations' => $this->terminations($users, $start, $end),
            'leaveStarts' => $this->leaveMovements($users, $start, $end, 'start_date'),
            'leaveEnds' => $this->leaveMovements($users, $start, $end, 'end_date'),
            'vacations' => $this->vacations($users, $start, $end),
            'shiftChanges' => $this->shiftChangesService->build($start, $end, $userIds),
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<array{employee: string, rut: string|null, position: string|null, premise: string|null, date: string}>
     */
    private function hires(Collection $users, Carbon $start, Carbon $end): array
    {
        return $this->employeeRows(
            $users->filter(fn (User $user): bool => $user->contract_start_date !== null
                && $user->contract_start_date->betweenIncluded($start, $end)),
            fn (User $user): string => $user->contract_start_date->format('d/m/Y'),
        );
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<array{employee: string, rut: string|null, position: string|null, premise: string|null, date: string}>
     */
    private function terminations(Collection $users, Carbon $start, Carbon $end): array
    {
        return $this->employeeRows(
            $users->filter(fn (User $user): bool => $user->contract_end_date !== null
                && $user->contract_end_date->betweenIncluded($start, $end)),
            fn (User $user): string => $user->contract_end_date->format('d/m/Y'),
        );
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  \Closure(User): string  $date
     * @return list<array{employee: string, rut: string|null, position: string|null, premise: string|null, date: string}>
     */
    private function employeeRows(Collection $users, \Closure $date): array
    {
        return array_values(array_map(
            fn (User $user): array => [
                'employee' => $user->name,
                'rut' => $user->formatted_rut ?? $user->rut,
                'position' => $user->position?->name,
                'premise' => $user->premise?->name,
                'date' => $date($user),
            ],
            $users->all(),
        ));
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  'start_date'|'end_date'  $column
     * @return list<array{employee: string, rut: string|null, type: string, startDate: string, endDate: string, days: float}>
     */
    private function leaveMovements(Collection $users, Carbon $start, Carbon $end, string $column): array
    {
        $leaves = Leave::query()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->where('type', '!=', LeaveType::Vacation)
            ->where('status', LeaveStatus::Approved)
            ->whereBetween($column, [$start->toDateString(), $end->toDateString()])
            ->orderBy($column)
            ->get();

        return $this->leaveRows($leaves, $users);
    }

    /**
     * Approved vacations overlapping the period: the range straddles either
     * boundary in either direction rather than starting or ending inside it,
     * since "vacaciones aprobadas" reports the vacation itself, not one of
     * its edges the way a licencia's start/end does.
     *
     * @param  Collection<int, User>  $users
     * @return list<array{employee: string, rut: string|null, type: string, startDate: string, endDate: string, days: float}>
     */
    private function vacations(Collection $users, Carbon $start, Carbon $end): array
    {
        $leaves = Leave::query()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->where('type', LeaveType::Vacation)
            ->where('status', LeaveStatus::Approved)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();

        return $this->leaveRows($leaves, $users);
    }

    /**
     * @param  Collection<int, Leave>  $leaves
     * @param  Collection<int, User>  $users
     * @return list<array{employee: string, rut: string|null, type: string, startDate: string, endDate: string, days: float}>
     */
    private function leaveRows(Collection $leaves, Collection $users): array
    {
        /** @var array<int, User> $usersById */
        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user->id] = $user;
        }

        return array_values(array_map(
            function (Leave $leave) use ($usersById): array {
                $user = $usersById[$leave->user_id];

                return [
                    'employee' => $user->name,
                    'rut' => $user->formatted_rut ?? $user->rut,
                    'type' => $leave->type->label(),
                    'startDate' => $leave->start_date->format('d/m/Y'),
                    'endDate' => $leave->end_date->format('d/m/Y'),
                    'days' => $leave->business_days_requested,
                ];
            },
            $leaves->all(),
        ));
    }
}
