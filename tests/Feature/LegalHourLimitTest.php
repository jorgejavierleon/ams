<?php

use App\Actions\CorrectLegalHourLimit;
use App\Enums\MarkType;
use App\Exceptions\LegalHourLimitIsAppendOnly;
use App\Exceptions\MissingLegalHourLimit;
use App\Models\LegalHourLimit;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use App\Models\Workday;
use App\Services\LegalHourLimitDrift;
use App\Services\LegalHourLimits;
use App\Services\LegalHourLimitVersions;
use App\Services\Reports\DailyReportService;
use App\Services\WorkdayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function limits(): LegalHourLimits
{
    return app(LegalHourLimits::class);
}

/**
 * An employee with a Monday–Friday 08:00–17:00 shift, punching in and out on
 * the given date so the calculator has a day to compute.
 *
 * @return array{0: User, 1: Carbon}
 */
function employeeWorkingOn(string $date): array
{
    $on = Carbon::parse($date)->startOfDay();

    $organization = Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);
    $shift = Shift::factory()->create(['organization_id' => $organization->id]);

    ShiftDay::factory()->create([
        'shift_id' => $shift->id,
        // ShiftDay weekdays are 0=Monday … 6=Sunday.
        'weekday' => (int) $on->format('N') - 1,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'lunch_start_time' => '12:00:00',
        'lunch_end_time' => '13:00:00',
        'is_free' => false,
    ]);

    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'start_date' => $on->copy()->subWeek()->toDateString(),
        'end_date' => null,
    ]);

    foreach ([[MarkType::In, 8], [MarkType::Out, 17]] as [$type, $hour]) {
        Mark::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'type' => $type,
            'date_time' => $on->copy()->setTime($hour, 0),
        ]);
    }

    return [$employee, $on];
}

describe('resolving a limit for a date', function () {
    it('resolves the version in force on the date, not the newest one', function (string $date, float $weekly) {
        expect(limits()->on(Carbon::parse($date))->ordinary_weekly_hours)->toBe($weekly);
    })->with([
        'before Ley 21.561' => ['2023-06-15', 45.0],
        'the day before the first step' => ['2024-04-25', 45.0],
        'the first step' => ['2024-04-26', 44.0],
        'the day before the second step' => ['2026-04-25', 44.0],
        'the second step' => ['2026-04-26', 42.0],
        'the day before the third step' => ['2028-04-25', 42.0],
        'the third step' => ['2028-04-26', 40.0],
        'after the last step' => ['2035-01-01', 40.0],
    ]);

    it('resolves the daily caps of the Código del Trabajo', function () {
        $today = limits()->on(Carbon::parse('2026-08-06'));

        expect($today->ordinary_daily_hours)->toBe(10.0)
            ->and($today->max_overtime_daily_hours)->toBe(2.0)
            ->and($today->max_overtime_weekly_hours)->toBe(12.0)
            ->and($today->max_total_daily_hours)->toBe(12.0)
            ->and($today->max_total_weekly_hours)->toBe(54.0);
    });

    it('refuses a date no version covers rather than borrowing the nearest rule', function () {
        limits()->on(Carbon::parse('2004-12-31'));
    })->throws(MissingLegalHourLimit::class);

    it('offers no way to ask for the current limits without naming a date', function () {
        $resolver = new ReflectionClass(LegalHourLimits::class);

        $names = collect($resolver->getMethods(ReflectionMethod::IS_PUBLIC))
            ->map(fn (ReflectionMethod $method): string => strtolower($method->getName()));

        // The whole bug class is someone reaching for the newest version when
        // they meant the applicable one. There must be nothing to reach for.
        expect($names)->not->toContain('current', 'today', 'now', 'latest', 'inforce', 'active');

        foreach (['on', 'forWeekOf', 'weekStart'] as $method) {
            expect($resolver->getMethod($method)->getNumberOfRequiredParameters())
                ->toBe(1, "{$method}() must require the date it is asked about");
        }
    });
});

describe('the week straddling a limit change', function () {
    // The 44-hour step took effect on Friday 26 April 2024, mid-week. The whole
    // Monday–Sunday week is judged against the version in force on its Monday,
    // so hours already lawfully worked on Monday to Thursday never turn into an
    // excess against a ceiling that did not exist when they were worked.
    it('judges the week against the version in force on its Monday', function (string $date, float $weekly) {
        expect(limits()->forWeekOf(Carbon::parse($date))->ordinary_weekly_hours)->toBe($weekly);
    })->with([
        'Monday of the straddling week' => ['2024-04-22', 45.0],
        'the Friday the change took effect' => ['2024-04-26', 45.0],
        'the Sunday closing that week' => ['2024-04-28', 45.0],
        'the Monday after' => ['2024-04-29', 44.0],
    ]);

    it('starts the week on Monday', function () {
        expect(LegalHourLimits::weekStart(Carbon::parse('2026-04-26'))->toDateString())
            ->toBe('2026-04-20');
    });
});

describe('append-only versions', function () {
    it('refuses an in-place edit of a recorded version', function () {
        $version = LegalHourLimit::query()->firstOrFail();

        $version->update(['ordinary_weekly_hours' => 40]);
    })->throws(LegalHourLimitIsAppendOnly::class);

    it('refuses to delete a recorded version', function () {
        LegalHourLimit::query()->firstOrFail()->delete();
    })->throws(LegalHourLimitIsAppendOnly::class);

    it('refuses a create that does not go through the append path', function () {
        LegalHourLimit::query()->create([
            'effective_from' => '2030-01-01',
            'ordinary_weekly_hours' => 38,
            'ordinary_daily_hours' => 10,
            'max_overtime_daily_hours' => 2,
            'max_overtime_weekly_hours' => 12,
            'max_total_daily_hours' => 12,
            'max_total_weekly_hours' => 50,
            'legal_reference' => 'Ley ficticia',
        ]);
    })->throws(LegalHourLimitIsAppendOnly::class);

    it('appends a new version without touching the ones already recorded', function () {
        $before = LegalHourLimit::query()->chronological()->get()->toArray();

        app(LegalHourLimitVersions::class)->add([
            'effective_from' => '2030-01-01',
            'ordinary_weekly_hours' => 38,
            'ordinary_daily_hours' => 10,
            'max_overtime_daily_hours' => 2,
            'max_overtime_weekly_hours' => 12,
            'max_total_daily_hours' => 12,
            'max_total_weekly_hours' => 50,
            'legal_reference' => 'Ley ficticia',
        ]);

        $after = LegalHourLimit::query()->chronological()->get()->take(count($before))->toArray();

        expect($after)->toBe($before)
            ->and(limits()->on(Carbon::parse('2030-01-01'))->ordinary_weekly_hours)->toBe(38.0)
            ->and(limits()->on(Carbon::parse('2029-12-31'))->ordinary_weekly_hours)->toBe(40.0);
    });

    it('leaves a past period reprinting byte-for-byte the same after a new version is added', function () {
        [$employee, $date] = employeeWorkingOn('2026-06-15');

        $reprint = function () use ($employee, $date): string {
            $report = app(DailyReportService::class)->build(
                $date->copy(), $date->copy()->addDays(4), [$employee->id],
            );

            // The reprint carries the limits the period was judged against, so
            // a version that moved them would show up as a different string.
            return json_encode([
                'report' => $report,
                'weekly_limit' => limits()->forWeekOf($date)->ordinary_weekly_hours,
                'daily_limit' => limits()->on($date)->ordinary_daily_hours,
            ], JSON_THROW_ON_ERROR);
        };

        $before = $reprint();

        app(LegalHourLimitVersions::class)->add([
            'effective_from' => '2030-01-01',
            'ordinary_weekly_hours' => 38,
            'ordinary_daily_hours' => 9,
            'max_overtime_daily_hours' => 2,
            'max_overtime_weekly_hours' => 12,
            'max_total_daily_hours' => 12,
            'max_total_weekly_hours' => 50,
            'legal_reference' => 'Ley ficticia',
        ]);

        expect($reprint())->toBe($before);
    });
});

describe('stamping the calculated day', function () {
    it('stamps the day with the version its own date resolves to', function () {
        [$employee, $date] = employeeWorkingOn('2024-04-24');

        app(WorkdayCalculator::class)->calculateDate($date);

        $workday = Workday::query()->where('user_id', $employee->id)->firstOrFail();

        expect($workday->legalHourLimit->ordinary_weekly_hours)->toBe(45.0);
    });

    it('stamps a day worked after a change with the newer version', function () {
        [$employee, $date] = employeeWorkingOn('2026-06-15');

        app(WorkdayCalculator::class)->calculateDate($date);

        $workday = Workday::query()->where('user_id', $employee->id)->firstOrFail();

        expect($workday->legalHourLimit->ordinary_weekly_hours)->toBe(42.0);
    });

    it('recalculating an old day after a newer version exists keeps the old day on its own rule', function () {
        [$employee, $date] = employeeWorkingOn('2024-04-24');

        app(WorkdayCalculator::class)->calculateDate($date);

        app(LegalHourLimitVersions::class)->add([
            'effective_from' => '2030-01-01',
            'ordinary_weekly_hours' => 38,
            'ordinary_daily_hours' => 10,
            'max_overtime_daily_hours' => 2,
            'max_overtime_weekly_hours' => 12,
            'max_total_daily_hours' => 12,
            'max_total_weekly_hours' => 50,
            'legal_reference' => 'Ley ficticia',
        ]);

        $workday = Workday::query()->where('user_id', $employee->id)->firstOrFail();
        app(WorkdayCalculator::class)->recalculateWorkday($workday);

        expect($workday->fresh()->legalHourLimit->ordinary_weekly_hours)->toBe(45.0);
    });

    it('reports no drift when every stamp agrees with its date', function () {
        [, $date] = employeeWorkingOn('2026-06-15');

        app(WorkdayCalculator::class)->calculateDate($date);

        expect(app(LegalHourLimitDrift::class)->exists())->toBeFalse();
    });

    it('detects a day stamped with a version its date does not resolve to', function () {
        $organization = Organization::factory()->create();
        $stale = LegalHourLimit::query()->chronological()->firstOrFail();

        $drifted = Workday::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->employee()->create(['organization_id' => $organization->id]),
            // A 2026 day carrying the 2005 version.
            'date' => '2026-06-15',
            'legal_hour_limit_id' => $stale->id,
        ]);

        expect(app(LegalHourLimitDrift::class)->detect()->pluck('id'))->toContain($drifted->id);
    });
});

describe('correcting a mistaken version', function () {
    it('refuses a correction with no written reason', function () {
        app(CorrectLegalHourLimit::class)->handle(
            LegalHourLimit::query()->firstOrFail(),
            ['ordinary_weekly_hours' => 43],
            '   ',
        );
    })->throws(InvalidArgumentException::class);

    it('applies the correction and recalculates every day it affected', function () {
        [$employee, $date] = employeeWorkingOn('2026-06-15');
        app(WorkdayCalculator::class)->calculateDate($date);

        $version = Workday::query()->where('user_id', $employee->id)->firstOrFail()->legalHourLimit;

        $recalculated = app(CorrectLegalHourLimit::class)->handle(
            $version,
            ['ordinary_weekly_hours' => 43],
            'Effective figure was typed as 42 instead of 43.',
        );

        expect($recalculated)->toBe(1)
            ->and($version->fresh()->ordinary_weekly_hours)->toBe(43.0)
            ->and(limits()->on($date)->ordinary_weekly_hours)->toBe(43.0);
    });

    it('restamps days a corrected effective date moved out of the version', function () {
        [$employee, $date] = employeeWorkingOn('2026-06-15');
        app(WorkdayCalculator::class)->calculateDate($date);

        $version = Workday::query()->where('user_id', $employee->id)->firstOrFail()->legalHourLimit;

        // The 42-hour step is corrected to start after the day was worked, so
        // that day belongs to the previous version now — and says so.
        app(CorrectLegalHourLimit::class)->handle(
            $version,
            ['effective_from' => '2026-07-01'],
            'Effective date was recorded three months early.',
        );

        $workday = Workday::query()->where('user_id', $employee->id)->firstOrFail();

        expect($workday->legalHourLimit->ordinary_weekly_hours)->toBe(44.0)
            ->and(app(LegalHourLimitDrift::class)->exists())->toBeFalse();
    });

    it('records the correction and its reason in the activity log', function () {
        $version = LegalHourLimit::query()->chronological()->firstOrFail();

        app(CorrectLegalHourLimit::class)->handle(
            $version,
            ['ordinary_weekly_hours' => 46],
            'Transcribed from the wrong article.',
        );

        $activity = Activity::query()->latest('id')->firstOrFail();

        expect($activity->event)->toBe('corrected')
            ->and($activity->properties['reason'])->toBe('Transcribed from the wrong article.')
            ->and($activity->properties['old']['ordinary_weekly_hours'])->toEqual(45);
    });
});

describe('tenant access', function () {
    it('lets tenant code read the limits it needs', function () {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user);

        expect(limits()->on(Carbon::parse('2026-08-06'))->ordinary_weekly_hours)->toBe(42.0);
    });

    it('gives even a tenant admin no write path to them', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole('admin');

        $this->actingAs($admin);

        // Tenant admins are super admins through the Gate::before in
        // AppServiceProvider, so authorization alone would let this through.
        // The refusal is structural instead: the model itself has no writable
        // path outside the SaaS-side flows.
        $version = LegalHourLimit::query()->firstOrFail();

        expect(fn () => $version->update(['ordinary_weekly_hours' => 60]))
            ->toThrow(LegalHourLimitIsAppendOnly::class)
            ->and(fn () => $version->delete())
            ->toThrow(LegalHourLimitIsAppendOnly::class)
            ->and(fn () => LegalHourLimit::query()->create(['effective_from' => '2031-01-01']))
            ->toThrow(LegalHourLimitIsAppendOnly::class);

        expect($version->fresh()->ordinary_weekly_hours)->toBe(45.0);
    });
});
