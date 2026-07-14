<?php

namespace App\Services;

use App\Enums\WorkdayStatus;
use App\Models\Workday;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls the raw attendance data for a given day — marks, the scheduled shift
 * and any approved leave — into one {@see Workday} row per employee, deriving
 * the status, worked/extra/missing time and the in/out shift deltas.
 *
 * The heavy lifting is a single set-based SQL query so a whole organization's
 * day can be computed in one pass. Time math relies on MySQL's TIMEDIFF/TIME
 * functions.
 */
class WorkdayCalculator
{
    /**
     * Compute and insert the workdays for every employee with attendance or a
     * scheduled shift on the given date. Existing rows for the date are skipped.
     */
    public function calculateDate(DateTimeInterface $date): bool
    {
        $success = true;

        $this->getWorkdayQuery($date)->chunk(100, function ($workdays) use (&$success): void {
            $insertData = $workdays->map(fn ($workday): array => [
                'date' => $workday->date,
                'user_id' => $workday->user_id,
                'organization_id' => $workday->organization_id,
                'company_id' => $workday->company_id,
                'premise_id' => $workday->premise_id,
                'mark_in_at' => $workday->mark_in_at,
                'mark_out_at' => $workday->mark_out_at,
                'mark_in_id' => $workday->mark_in_id,
                'mark_out_id' => $workday->mark_out_id,
                'leave_id' => $workday->leave_id,
                'shift_start_time' => $workday->shift_start_time,
                'shift_end_time' => $workday->shift_end_time,
                'shift_id' => $workday->shift_id,
                'in_time_difference' => $workday->in_time_difference,
                'out_time_difference' => $workday->out_time_difference,
                'worked_time' => $this->calculateWorkedTime($workday),
                'status' => $this->getStatus($workday),
                'extra_time' => $workday->extra_time,
                'missing_time' => $workday->missing_time,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (! Workday::query()->insert($insertData)) {
                $success = false;
            }
        });

        return $success;
    }

    /**
     * Recompute a single workday in place (e.g. after a mark modification).
     */
    public function recalculateWorkday(Workday $workday): bool
    {
        $data = $this->getWorkdayQuery($workday->date, $workday->id)
            ->get()
            ->map(fn ($row): array => [
                'premise_id' => $row->premise_id,
                'mark_in_at' => $row->mark_in_at,
                'mark_out_at' => $row->mark_out_at,
                'mark_in_id' => $row->mark_in_id,
                'mark_out_id' => $row->mark_out_id,
                'shift_start_time' => $row->shift_start_time,
                'shift_end_time' => $row->shift_end_time,
                'shift_id' => $row->shift_id,
                'leave_id' => $row->leave_id,
                'in_time_difference' => $row->in_time_difference,
                'out_time_difference' => $row->out_time_difference,
                'worked_time' => $this->calculateWorkedTime($row),
                'status' => $this->getStatus($row),
                'extra_time' => $row->extra_time,
                'missing_time' => $row->missing_time,
            ])
            ->first();

        if ($data === null) {
            return false;
        }

        return $workday->update($data);
    }

    /**
     * The set-based query joining users to their marks, scheduled shift day and
     * approved leave for the date, producing one candidate workday row per user.
     *
     * ShiftDay weekdays are 0=Monday … 6=Sunday, so the join keys off
     * `format('N') - 1` (ISO day, 1=Monday) rather than Carbon's `dayOfWeek`.
     */
    protected function getWorkdayQuery(DateTimeInterface $date, ?int $workdayId = null): Builder
    {
        $dateString = Carbon::parse($date)->toDateString();
        $weekday = (int) Carbon::parse($date)->format('N') - 1;

        $query = DB::table('users')
            ->leftJoin('marks as mark_in', function ($join) use ($date): void {
                $join->on('users.id', '=', 'mark_in.user_id')
                    ->whereDate('mark_in.date_time', '=', $date)
                    ->where('mark_in.type', '=', 'in')
                    ->whereNull('mark_in.deleted_at');
            })
            ->leftJoin('marks as mark_out', function ($join) use ($date): void {
                $join->on('users.id', '=', 'mark_out.user_id')
                    ->whereDate('mark_out.date_time', '=', $date)
                    ->where('mark_out.type', '=', 'out')
                    ->whereNull('mark_out.deleted_at');
            })
            ->leftJoin('shift_assignments', function ($join) use ($date): void {
                $join->on('users.id', '=', 'shift_assignments.user_id')
                    ->whereDate('shift_assignments.start_date', '<=', $date)
                    ->where(function ($query) use ($date): void {
                        $query->whereNull('shift_assignments.end_date')
                            ->orWhereDate('shift_assignments.end_date', '>=', $date);
                    });
            })
            ->leftJoin('shift_days', function ($join) use ($weekday): void {
                $join->on('shift_assignments.shift_id', '=', 'shift_days.shift_id')
                    ->where('shift_days.weekday', '=', $weekday)
                    ->where('shift_days.is_free', '=', false);
            })
            ->leftJoin('leaves', function ($join) use ($date): void {
                $join->on('users.id', '=', 'leaves.user_id')
                    ->whereRaw('leaves.id = (select MIN(id) from leaves where user_id = users.id and status = "approved" and start_date <= ? and end_date >= ? limit 1)', [$date, $date]);
            })
            ->leftJoin('shifts', 'shifts.id', '=', 'shift_days.shift_id')
            ->leftJoin('workdays', function ($join) use ($date): void {
                $join->on('users.id', '=', 'workdays.user_id')
                    ->whereDate('workdays.date', '=', $date);
            })
            ->whereNotNull('users.organization_id')
            ->where(function ($query): void {
                $query->whereNotNull('mark_in.date_time')
                    ->orWhereNotNull('mark_out.date_time')
                    ->orWhereNotNull('shift_days.id');
            })
            ->select([
                DB::raw("'{$dateString}' as date"),
                'users.id as user_id',
                'users.company_id as company_id',
                'users.organization_id as organization_id',
                'users.premise_id as premise_id',
                'mark_in.date_time as mark_in_at',
                'mark_out.date_time as mark_out_at',
                'mark_in.id as mark_in_id',
                'mark_out.id as mark_out_id',
                'shift_days.start_time as shift_start_time',
                'shift_days.end_time as shift_end_time',
                'shift_days.shift_id',
                'shift_days.lunch_start_time as lunch_start_time',
                'shift_days.lunch_end_time as lunch_end_time',
                'leaves.id as leave_id',
                DB::raw('TIMEDIFF(TIME(mark_in.date_time), shift_days.start_time) as in_time_difference'),
                DB::raw('TIMEDIFF(TIME(mark_out.date_time), shift_days.end_time) as out_time_difference'),
                DB::raw("
                    CASE
                        WHEN shift_days.end_time IS NULL OR shift_days.start_time IS NULL
                            THEN TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time))
                        WHEN TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time)) > TIMEDIFF(shift_days.end_time, shift_days.start_time)
                            THEN TIMEDIFF(
                                    TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time)),
                                    TIMEDIFF(shift_days.end_time, shift_days.start_time)
                                 )
                        ELSE '00:00:00'
                    END as extra_time
                "),
                DB::raw("
                    CASE
                        WHEN TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time)) < TIMEDIFF(shift_days.end_time, shift_days.start_time)
                            THEN TIMEDIFF(
                                    TIMEDIFF(shift_days.end_time, shift_days.start_time),
                                    TIMEDIFF(TIME(mark_out.date_time), TIME(mark_in.date_time))
                                 )
                        ELSE '00:00:00'
                    END as missing_time
                "),
            ])
            ->orderBy('users.id')
            ->distinct();

        // A workday id means we are recomputing that single row; otherwise only
        // days without an existing workday are computed.
        if ($workdayId !== null) {
            $query->where('workdays.id', $workdayId);
        } else {
            $query->whereNull('workdays.id');
        }

        return $query;
    }

    /**
     * Derive the attendance status from the presence of marks, a shift and a
     * leave for the day.
     */
    protected function getStatus(object $workday): ?string
    {
        // An approved leave justifies the whole day.
        if ($workday->leave_id !== null) {
            return WorkdayStatus::Justified->value;
        }

        // Marks with no scheduled shift are irregular attendance.
        if (
            $workday->shift_id === null
            && ($workday->mark_in_id !== null || $workday->mark_out_id !== null)
        ) {
            return WorkdayStatus::Irregular->value;
        }

        // Both marks against a scheduled shift is a regular workday.
        if ($workday->mark_in_id !== null && $workday->mark_out_id !== null && $workday->shift_id !== null) {
            return WorkdayStatus::Regular->value;
        }

        // A scheduled shift with no marks at all is an absence.
        if ($workday->mark_in_id === null && $workday->mark_out_id === null && $workday->shift_id !== null) {
            return WorkdayStatus::Absent->value;
        }

        // Only one of the two marks: an incomplete day.
        if ($workday->mark_in_id !== null || $workday->mark_out_id !== null) {
            return WorkdayStatus::Incomplete->value;
        }

        return null;
    }

    /**
     * Worked time (HH:MM:SS) between the in and out marks, excluding the
     * scheduled lunch break when one is defined.
     */
    protected function calculateWorkedTime(object $workday): string
    {
        if ($workday->mark_in_at === null || $workday->mark_out_at === null) {
            return '00:00:00';
        }

        $markIn = $this->toCarbon($workday->mark_in_at);
        $markOut = $this->toCarbon($workday->mark_out_at);
        $lunchStart = $this->toCarbon($workday->lunch_start_time ?? null);
        $lunchEnd = $this->toCarbon($workday->lunch_end_time ?? null);

        $seconds = $markIn->diffInSeconds($markOut);

        if ($lunchStart !== null && $lunchEnd !== null) {
            $seconds -= $lunchStart->diffInSeconds($lunchEnd);
        }

        return gmdate('H:i:s', (int) $seconds);
    }

    private function toCarbon(string|Carbon|null $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
