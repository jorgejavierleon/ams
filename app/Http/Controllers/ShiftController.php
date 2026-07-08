<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\ShiftType;
use App\Models\Shift;
use App\Models\ShiftDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'type', 'total_week_hours', 'created_at'],
            'name',
        );

        $shifts = Shift::query()
            ->withCount('activeShiftAssignments')
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('shifts/index', [
            'shifts' => $shifts->through(fn (Shift $shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'type' => $shift->type->label(),
                'total_week_hours' => $shift->total_week_hours,
                'exceeds_max' => $shift->total_week_hours > config('ams.max_weekly_hours'),
                'assignments_count' => $shift->active_shift_assignments_count,
                'is_default' => $shift->is_default,
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
            'maxWeeklyHours' => (int) config('ams.max_weekly_hours'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('shifts/create', [
            'types' => ShiftType::options(),
            'defaultDays' => $this->defaultDays(),
            'maxWeeklyHours' => (int) config('ams.max_weekly_hours'),
            'maxDailyHours' => (int) config('ams.max_daily_hours'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateShift($request);

        DB::transaction(function () use ($data) {
            // Creating the shift fires ShiftObserver, which seeds the 7 default
            // days; we then apply the submitted values on top of them.
            $shift = Shift::create(Arr::except($data, 'days'));
            $this->syncDays($shift, $data['days']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.shifts.flash.created')]);

        return to_route('shifts.index');
    }

    public function edit(Shift $shift): Response
    {
        $shift->load(['days' => fn ($query) => $query->orderBy('weekday')]);

        return Inertia::render('shifts/edit', [
            'shift' => [
                'id' => $shift->id,
                'name' => $shift->name,
                'type' => $shift->type->value,
                'description' => $shift->description,
                'tolerance_in' => $this->timeToMinutes($shift->tolerance_in),
                'tolerance_out' => $this->timeToMinutes($shift->tolerance_out),
                'work_on_holidays' => $shift->work_on_holidays,
                'is_archive' => $shift->is_archive,
                'is_default' => $shift->is_default,
                'total_week_hours' => $shift->total_week_hours,
                'days' => $shift->days->map(fn (ShiftDay $day) => [
                    'weekday' => $day->weekday,
                    'start_time' => $day->start_time?->format('H:i'),
                    'end_time' => $day->end_time?->format('H:i'),
                    'lunch_start_time' => $day->lunch_start_time?->format('H:i'),
                    'lunch_end_time' => $day->lunch_end_time?->format('H:i'),
                    'is_free' => $day->is_free,
                    'total_work_hours' => $day->total_work_hours,
                ])->all(),
            ],
            'types' => ShiftType::options(),
            'maxWeeklyHours' => (int) config('ams.max_weekly_hours'),
            'maxDailyHours' => (int) config('ams.max_daily_hours'),
        ]);
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $data = $this->validateShift($request);

        DB::transaction(function () use ($shift, $data) {
            $shift->update(Arr::except($data, 'days'));
            $this->syncDays($shift, $data['days']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.shifts.flash.updated')]);

        return to_route('shifts.index');
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        if ($shift->activeShiftAssignments()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.shifts.flash.has_assignments')]);

            return back();
        }

        $shift->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.shifts.flash.deleted')]);

        return to_route('shifts.index');
    }

    /**
     * Apply the submitted per-day values onto the shift's existing day rows,
     * matched by weekday so the observers still fire (never bypassed).
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    private function syncDays(Shift $shift, array $days): void
    {
        $existing = $shift->days()->get()->keyBy('weekday');

        foreach ($days as $day) {
            $existing->get($day['weekday'])?->update([
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
                'lunch_start_time' => $day['lunch_start_time'],
                'lunch_end_time' => $day['lunch_end_time'],
                'is_free' => $day['is_free'],
            ]);
        }
    }

    /**
     * Validate the shift payload, including the legal weekly-hours ceiling.
     *
     * @return array<string, mixed>
     */
    private function validateShift(Request $request): array
    {
        $days = array_values($request->input('days', []));

        $request->merge([
            'days' => $days,
            'work_on_holidays' => $request->boolean('work_on_holidays'),
            'is_archive' => $request->boolean('is_archive'),
            'is_default' => $request->boolean('is_default'),
        ]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ShiftType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            // Tolerance is a grace period entered in minutes; it is stored as a
            // TIME the WorkdayCalculator can compare against a mark's lateness.
            'tolerance_in' => ['nullable', 'integer', 'min:0', 'max:720'],
            'tolerance_out' => ['nullable', 'integer', 'min:0', 'max:720'],
            'work_on_holidays' => ['boolean'],
            'is_archive' => ['boolean'],
            'is_default' => ['boolean'],
            'days' => ['required', 'array', 'size:7'],
        ];

        foreach ($days as $index => $day) {
            $rules["days.{$index}.weekday"] = ['required', 'integer', 'between:0,6'];
            $rules["days.{$index}.is_free"] = ['boolean'];

            $timeRules = empty($day['is_free'])
                ? ['required', 'date_format:H:i']
                : ['nullable', 'date_format:H:i'];

            $rules["days.{$index}.start_time"] = $timeRules;
            $rules["days.{$index}.end_time"] = $timeRules;
            $rules["days.{$index}.lunch_start_time"] = $timeRules;
            $rules["days.{$index}.lunch_end_time"] = $timeRules;
        }

        $validated = $request->validate($rules);

        $validated['tolerance_in'] = $this->minutesToTime($validated['tolerance_in'] ?? null);
        $validated['tolerance_out'] = $this->minutesToTime($validated['tolerance_out'] ?? null);

        $validated['days'] = array_map(function (array $day): array {
            $day['is_free'] = ! empty($day['is_free']);

            return $day;
        }, $validated['days']);

        $this->assertWithinLegalHours($validated['days']);

        return $validated;
    }

    /**
     * Guard the legal weekly maximum and reject days with negative duration.
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    private function assertWithinLegalHours(array $days): void
    {
        $weeklyHours = 0.0;

        foreach ($days as $index => $day) {
            if ($day['is_free']) {
                continue;
            }

            $dailyHours = $this->dailyHours($day);

            if ($dailyHours < 0) {
                throw ValidationException::withMessages([
                    "days.{$index}.end_time" => __('ui.shifts.validation.negative_hours'),
                ]);
            }

            $weeklyHours += $dailyHours;
        }

        if ($weeklyHours > config('ams.max_weekly_hours')) {
            throw ValidationException::withMessages([
                'days' => __('ui.shifts.validation.exceeds_weekly', [
                    'max' => config('ams.max_weekly_hours'),
                    'total' => rtrim(rtrim(number_format($weeklyHours, 2), '0'), '.'),
                ]),
            ]);
        }
    }

    /**
     * Worked hours for a single day: (end - start) minus the lunch break.
     *
     * @param  array<string, mixed>  $day
     */
    private function dailyHours(array $day): float
    {
        $start = Carbon::createFromFormat('H:i', $day['start_time']);
        $end = Carbon::createFromFormat('H:i', $day['end_time']);
        $lunchStart = Carbon::createFromFormat('H:i', $day['lunch_start_time']);
        $lunchEnd = Carbon::createFromFormat('H:i', $day['lunch_end_time']);

        $minutes = $start->diffInMinutes($end, false)
            - $lunchStart->diffInMinutes($lunchEnd, false);

        return $minutes / 60;
    }

    /**
     * Convert a minutes count into the `H:i:s` TIME string stored on the shift.
     */
    private function minutesToTime(int|string|null $minutes): ?string
    {
        if ($minutes === null || $minutes === '') {
            return null;
        }

        $minutes = (int) $minutes;

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Convert a stored `H:i:s` tolerance back into whole minutes for the form.
     */
    private function timeToMinutes(?string $time): ?int
    {
        if ($time === null) {
            return null;
        }

        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }

    /**
     * The default Mon–Sun schedule mirrored from ShiftObserver, used to
     * pre-fill the create form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultDays(): array
    {
        return array_map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'is_free' => $weekday === 5 || $weekday === 6,
        ], range(0, 6));
    }
}
