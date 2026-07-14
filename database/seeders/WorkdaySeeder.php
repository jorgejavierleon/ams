<?php

namespace Database\Seeders;

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Managers\MarkManager;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use App\Services\WorkdayCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Populate the Workdays list with a fortnight of computed attendance for the
 * demo organization: a spread of regular, absent and incomplete days plus a
 * few pending mark modifications so the pending indicator has data to show.
 */
class WorkdaySeeder extends Seeder
{
    /**
     * How many days back to generate attendance for.
     */
    private const DAYS = 14;

    public function run(WorkdayCalculator $calculator, MarkManager $markManager): void
    {
        $organization = Organization::query()
            ->where('slug', 'demo-organization')
            ->first();

        if ($organization === null) {
            return;
        }

        $employees = User::query()
            ->employees()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        foreach ($this->workingDays() as $date) {
            $this->seedMarksForDate($employees, $date, $markManager);
            $calculator->calculateDate($date);
        }

        $this->seedPendingModifications($organization);
    }

    /**
     * The last {@see self::DAYS} weekdays (Mon–Fri), oldest first.
     *
     * @return array<int, Carbon>
     */
    private function workingDays(): array
    {
        $days = [];

        for ($offset = self::DAYS; $offset >= 1; $offset--) {
            $date = Carbon::today()->subDays($offset);

            if (! $date->isWeekend()) {
                $days[] = $date;
            }
        }

        return $days;
    }

    /**
     * Register in/out punches for the day relative to each employee's scheduled
     * shift, deterministically varying who is on time, late, absent or leaves
     * early so the statuses spread out.
     *
     * @param  Collection<int, User>  $employees
     */
    private function seedMarksForDate($employees, Carbon $date, MarkManager $markManager): void
    {
        foreach ($employees as $index => $employee) {
            $shift = $markManager->getShiftForDate($employee, $date);

            // No scheduled shift that day (e.g. a free day): nothing to punch.
            if ($shift === null) {
                continue;
            }

            // Rotate the pattern by day so a given employee is not always the
            // one who is absent.
            $pattern = ($index + $date->dayOfYear) % 6;

            // Absent: a scheduled shift with no marks at all.
            if ($pattern === 0) {
                continue;
            }

            $start = Carbon::parse($shift['start_time']);
            $end = Carbon::parse($shift['end_time']);

            // "Late" arrival (12 min) on pattern 1, otherwise a couple minutes in.
            $markIn = $date->copy()->setTime($start->hour, $start->minute)
                ->addMinutes($pattern === 1 ? 12 : 2);
            $this->mark($employee, MarkType::In, $markIn);

            // Incomplete: only the in-mark is registered.
            if ($pattern === 2) {
                continue;
            }

            // "Early" departure (20 min) on pattern 3, otherwise a few minutes over.
            $markOut = $date->copy()->setTime($end->hour, $end->minute)
                ->addMinutes($pattern === 3 ? -20 : 5);
            $this->mark($employee, MarkType::Out, $markOut);
        }
    }

    private function mark(User $employee, MarkType $type, Carbon $at): void
    {
        // DatabaseSeeder mutes model events, so neither MarkObserver nor the
        // organization auto-stamp fire here; set the guarded columns directly
        // and reproduce the observer's checksum formula.
        $mark = new Mark([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'premise_id' => $employee->premise_id,
            'type' => $type,
            'date_time' => $at,
            'original_date_time' => $at,
        ]);
        $mark->organization_id = $employee->organization_id;
        $mark->checksum = hash('sha256', $employee->id.$type->value.$at->toIso8601String());
        $mark->save();
    }

    /**
     * Flag a handful of the most recent regular workdays with a pending mark
     * modification so the list's pending indicator is exercised.
     */
    private function seedPendingModifications(Organization $organization): void
    {
        $workdays = Workday::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('mark_in_id')
            ->latest('date')
            ->take(3)
            ->get();

        foreach ($workdays as $workday) {
            // Events are muted during seeding, so the model's ULID hook does not
            // fire; generate the public identifier explicitly.
            MarkModification::query()->create([
                'organization_id' => $organization->id,
                'workday_id' => $workday->id,
                'mark_id' => $workday->mark_in_id,
                'user_id' => $workday->user_id,
                'reason' => MarkModificationReason::MarkForgotten,
                'status' => MarkModificationStatus::Pending,
                'date_time' => $workday->date->copy()->setTime(8, 0),
                'mark_type' => MarkType::In,
                'notes' => 'Solicitud de corrección de marca de entrada.',
                'ulid' => strtolower((string) Str::ulid()),
            ]);
        }
    }
}
