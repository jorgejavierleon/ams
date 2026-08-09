<?php

use App\Enums\LeaveStatus;
use App\Enums\MarkType;
use App\Enums\OvertimeCalculationState;
use App\Events\WorkdaysRecalculationNeeded;
use App\Jobs\CalculateOvertime;
use App\Models\Leave;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * An employee on an 08:00–17:00 shift (no lunch, so worked time is easy to read)
 * in their own organization, on the coming Monday.
 *
 * @return array{0: User, 1: Carbon, 2: Organization}
 */
function overtimeEmployee(?Organization $organization = null): array
{
    $organization ??= Organization::factory()->create();
    $employee = User::factory()->employee()->create(['organization_id' => $organization->id]);

    $date = Carbon::parse('next monday')->startOfDay();

    $shift = Shift::factory()->create(['organization_id' => $organization->id]);

    // A new shift is seeded with a row for every weekday; the one being tested
    // is reshaped rather than added, so the calculator sees a single candidate.
    // ShiftDay weekdays are 0=Monday … 6=Sunday.
    $shift->days()
        ->where('weekday', (int) $date->format('N') - 1)
        ->firstOrFail()
        ->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'lunch_start_time' => null,
            'lunch_end_time' => null,
            'is_free' => false,
        ]);

    ShiftAssignment::factory()->create([
        'organization_id' => $organization->id,
        'shift_id' => $shift->id,
        'user_id' => $employee->id,
        'start_date' => $date->copy()->subWeek()->toDateString(),
        'end_date' => null,
    ]);

    return [$employee, $date, $organization];
}

function overtimePunch(User $employee, MarkType $type, Carbon $at): Mark
{
    return Mark::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'type' => $type,
        'date_time' => $at,
    ]);
}

function overtimeWorkday(User $employee): Workday
{
    return Workday::withoutGlobalScopes()->where('user_id', $employee->id)->sole();
}

/**
 * The figures a re-run must reproduce byte for byte when no input has moved.
 * `updated_at` and `overtime_calculated_at` are deliberately absent — they
 * record *that* the engine ran, which is the one thing a re-run does change.
 *
 * @return array<string, mixed>
 */
function overtimeFigures(Workday $workday): array
{
    return $workday->only([
        'status', 'worked_time', 'extra_time', 'missing_time',
        'in_time_difference', 'out_time_difference',
        'pre_shift_excess', 'post_shift_excess', 'calculated_overtime',
        'overtime_state', 'legal_hour_limit_id', 'mark_in_id', 'mark_out_id',
    ]);
}

test('the job computes a day of overtime and can reach no state higher than pending review', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    $workday = overtimeWorkday($employee);

    expect($workday->calculated_overtime)->toBe('02:30:00')
        ->and($workday->post_shift_excess)->toBe('02:30:00')
        ->and($workday->overtime_state)->toBe(OvertimeCalculationState::PendingReview)
        ->and($workday->overtime_calculated_at)->not->toBeNull()
        ->and($workday->overtime_decided_at)->toBeNull();
});

test('a day with no overtime to review is computed but is not put in the review queue', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(overtimeWorkday($employee)->overtime_state)->toBe(OvertimeCalculationState::NotApplicable);
});

test('the calculation engine has no approved state to write', function () {
    // The guarantee of PRD §7.2 is structural rather than conventional: the
    // engine's vocabulary contains no approved case, so there is no value a
    // refactor or a backfill could hand it that would produce a payable hour.
    // The cast refuses one outright.
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(OvertimeCalculationState::tryFrom('approved'))->toBeNull()
        ->and(OvertimeCalculationState::cases())->toBe([
            OvertimeCalculationState::NotApplicable,
            OvertimeCalculationState::PendingReview,
        ])
        ->and(fn () => overtimeWorkday($employee)->update(['overtime_state' => 'approved']))
        ->toThrow(ValueError::class);
});

test('re-running the job for a processed date updates the row instead of adding a second one', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    $first = overtimeFigures(overtimeWorkday($employee));
    $id = overtimeWorkday($employee)->id;

    dispatch_sync(new CalculateOvertime($organization->id, $date));
    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(Workday::withoutGlobalScopes()->count())->toBe(1)
        ->and(overtimeWorkday($employee)->id)->toBe($id)
        ->and(overtimeFigures(overtimeWorkday($employee)))->toBe($first);
});

test('re-running after a mark is corrected updates the calculated overtime', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    $markOut = overtimePunch($employee, MarkType::Out, $date->copy()->setTime(17, 0));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(overtimeWorkday($employee)->calculated_overtime)->toBe('00:00:00');

    $markOut->update(['date_time' => $date->copy()->setTime(20, 15)]);

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(overtimeWorkday($employee)->calculated_overtime)->toBe('03:15:00')
        ->and(overtimeWorkday($employee)->overtime_state)->toBe(OvertimeCalculationState::PendingReview)
        ->and(Workday::withoutGlobalScopes()->count())->toBe(1);
});

test('a day already decided keeps its decision and surfaces as needing re-review when the figure moves', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    $markOut = overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 0));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    // A human signs off on the two hours the engine found.
    $decidedAt = Carbon::parse('2026-01-15 09:30:00');
    overtimeWorkday($employee)->update([
        'overtime_decided_at' => $decidedAt,
        'overtime_decided_value' => '02:00:00',
    ]);

    expect(overtimeWorkday($employee)->overtimeNeedsReReview())->toBeFalse();

    // The exit mark turns out to have been an hour later than recorded.
    $markOut->update(['date_time' => $date->copy()->setTime(20, 0)]);

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    $workday = overtimeWorkday($employee);

    expect($workday->calculated_overtime)->toBe('03:00:00')
        ->and($workday->overtime_decided_value)->toBe('02:00:00')
        ->and($workday->overtime_decided_at->toDateTimeString())->toBe($decidedAt->toDateTimeString())
        ->and($workday->overtimeNeedsReReview())->toBeTrue()
        ->and(Workday::withoutGlobalScopes()->needsOvertimeReReview()->pluck('id')->all())->toBe([$workday->id]);
});

test('a decided day whose figure disappears entirely still surfaces for re-review', function () {
    // A correction that leaves the day with a single mark takes the calculated
    // overtime to null, not to zero. A `!=` comparison would silently drop the
    // day; the reviewer has to see it.
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    $markOut = overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 0));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    overtimeWorkday($employee)->update([
        'overtime_decided_at' => now(),
        'overtime_decided_value' => '02:00:00',
    ]);

    $markOut->delete();

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    $workday = overtimeWorkday($employee);

    expect($workday->calculated_overtime)->toBeNull()
        ->and($workday->overtime_state)->toBe(OvertimeCalculationState::NotApplicable)
        ->and($workday->overtime_decided_value)->toBe('02:00:00')
        ->and($workday->overtimeNeedsReReview())->toBeTrue()
        ->and(Workday::withoutGlobalScopes()->needsOvertimeReReview()->count())->toBe(1);
});

test('a decided day left alone by a re-run is not flagged for re-review', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 0));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    overtimeWorkday($employee)->update([
        'overtime_decided_at' => now(),
        'overtime_decided_value' => '02:00:00',
    ]);

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(overtimeWorkday($employee)->overtimeNeedsReReview())->toBeFalse()
        ->and(Workday::withoutGlobalScopes()->needsOvertimeReReview()->count())->toBe(0);
});

test('the calculated day can be traced back to the marks it was derived from', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    $markIn = overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    $markOut = overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    $workday = overtimeWorkday($employee);

    expect($workday->sourceMarks()->pluck('id')->all())->toBe([$markIn->id, $markOut->id])
        ->and($workday->sourceMarks()->pluck('folio')->filter()->count())->toBe(2);
});

test('the job runs over a date range for a backfill', function () {
    [$employee, $monday, $organization] = overtimeEmployee();
    $wednesday = $monday->copy()->addDays(2);

    foreach ([$monday, $wednesday] as $day) {
        overtimePunch($employee, MarkType::In, $day->copy()->setTime(8, 0));
        overtimePunch($employee, MarkType::Out, $day->copy()->setTime(19, 0));
    }

    dispatch_sync(new CalculateOvertime($organization->id, $monday, $monday->copy()->addDays(6)));

    $workdays = Workday::withoutGlobalScopes()->orderBy('date')->get();

    // Five rows, not seven: the shift's weekend days are free, and a free day
    // with no marks is not a workday at all.
    expect($workdays)->toHaveCount(5)
        ->and($workdays->firstWhere('date.timestamp', $monday->timestamp)->calculated_overtime)->toBe('02:00:00')
        ->and($workdays->firstWhere('date.timestamp', $wednesday->timestamp)->calculated_overtime)->toBe('02:00:00')
        ->and($workdays->where('overtime_state', OvertimeCalculationState::PendingReview))->toHaveCount(2);
});

test('a backfill over an already-processed range corrects it rather than duplicating it', function () {
    [$employee, $monday, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $monday->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $monday->copy()->setTime(19, 0));

    dispatch_sync(new CalculateOvertime($organization->id, $monday));
    dispatch_sync(new CalculateOvertime($organization->id, $monday->copy()->subDays(3), $monday->copy()->addDays(3)));

    expect(Workday::withoutGlobalScopes()->where('date', $monday->toDateString())->count())->toBe(1);
});

test('a backwards range is clamped to the start date rather than silently processing nothing', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 0));

    dispatch_sync(new CalculateOvertime($organization->id, $date, $date->copy()->subWeek()));

    expect(overtimeWorkday($employee)->calculated_overtime)->toBe('02:00:00');
});

test('processing one tenant never touches another', function () {
    [$ours, $date, $organization] = overtimeEmployee();
    [$theirs] = overtimeEmployee();

    foreach ([$ours, $theirs] as $employee) {
        overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
        overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));
    }

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(Workday::withoutGlobalScopes()->count())->toBe(1)
        ->and(overtimeWorkday($ours)->organization_id)->toBe($organization->id)
        ->and(Workday::withoutGlobalScopes()->where('user_id', $theirs->id)->exists())->toBeFalse();
});

test('a re-run of one tenant leaves another tenant already-computed day untouched', function () {
    [$ours, $date, $organization] = overtimeEmployee();
    [$theirs, , $otherOrganization] = overtimeEmployee();

    foreach ([$ours, $theirs] as $employee) {
        overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
        overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));
    }

    dispatch_sync(new CalculateOvertime($otherOrganization->id, $date));

    $theirWorkday = overtimeWorkday($theirs);
    $theirCalculatedAt = $theirWorkday->overtime_calculated_at;

    $this->travel(1)->minutes();
    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(overtimeWorkday($theirs)->overtime_calculated_at->toDateTimeString())
        ->toBe($theirCalculatedAt->toDateTimeString());
});

test('an approved leave recalculates the affected days it already computed', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));

    dispatch_sync(new CalculateOvertime($organization->id, $date));

    expect(overtimeWorkday($employee)->leave_id)->toBeNull();

    $leave = Leave::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'start_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
        'status' => LeaveStatus::Approved,
    ]);

    expect(overtimeWorkday($employee)->leave_id)->toBe($leave->id);
});

test('a shift assignment change dispatches a recalculation scoped to its own tenant', function () {
    [$employee, $date, $organization] = overtimeEmployee();

    $assignment = ShiftAssignment::withoutGlobalScopes()->where('user_id', $employee->id)->sole();

    // Faked only now, so the assignment's own creation is not counted.
    Queue::fake();

    $assignment->update(['end_date' => $date->toDateString()]);

    Queue::assertPushed(CalculateOvertime::class, function (CalculateOvertime $job) use ($employee, $organization): bool {
        return $job->organizationId === $organization->id
            && $job->userIds === [$employee->id]
            && $job->onlyComputedDays === true;
    });
});

test('a recalculation spanning two tenants is dispatched as one job each', function () {
    [$ours, $date, $organization] = overtimeEmployee();
    [$theirs, , $otherOrganization] = overtimeEmployee();

    Queue::fake();

    event(new WorkdaysRecalculationNeeded(collect([$ours->id, $theirs->id]), $date, $date));

    Queue::assertPushed(CalculateOvertime::class, 2);
    Queue::assertPushed(
        CalculateOvertime::class,
        fn (CalculateOvertime $job): bool => $job->organizationId === $organization->id && $job->userIds === [$ours->id],
    );
    Queue::assertPushed(
        CalculateOvertime::class,
        fn (CalculateOvertime $job): bool => $job->organizationId === $otherOrganization->id && $job->userIds === [$theirs->id],
    );
});

test('an event-driven recalculation does not backfill days nobody ever computed', function () {
    // An assignment backdated a month is not an instruction to manufacture a
    // month of absences; only days already rolled up are recomputed.
    [$employee, $date, $organization] = overtimeEmployee();

    overtimePunch($employee, MarkType::In, $date->copy()->setTime(8, 0));
    overtimePunch($employee, MarkType::Out, $date->copy()->setTime(19, 30));

    dispatch_sync(new CalculateOvertime(
        $organization->id,
        $date->copy()->subWeek(),
        $date,
        [$employee->id],
        onlyComputedDays: true,
    ));

    expect(Workday::withoutGlobalScopes()->count())->toBe(0);
});

test('the close-out command queues one job per organization for yesterday', function () {
    Queue::fake();

    $organizations = Organization::factory()->count(3)->create();

    $this->artisan('overtime:calculate')->assertSuccessful();

    Queue::assertPushed(CalculateOvertime::class, $organizations->count());
    Queue::assertPushed(
        CalculateOvertime::class,
        fn (CalculateOvertime $job): bool => $job->startDate->isSameDay(now()->subDay())
            && $job->endDate->isSameDay(now()->subDay())
            && $job->onlyComputedDays === false,
    );
});

test('the command backfills a range for a single organization on request', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    Organization::factory()->create();

    $this->artisan('overtime:calculate', [
        '--organization' => [$organization->id],
        '--from' => '2026-03-01',
        '--to' => '2026-03-31',
    ])->assertSuccessful();

    Queue::assertPushed(CalculateOvertime::class, 1);
    Queue::assertPushed(
        CalculateOvertime::class,
        fn (CalculateOvertime $job): bool => $job->organizationId === $organization->id
            && $job->startDate->toDateString() === '2026-03-01'
            && $job->endDate->toDateString() === '2026-03-31',
    );
});

test('the command refuses a range that ends before it starts', function () {
    Queue::fake();

    Organization::factory()->create();

    $this->artisan('overtime:calculate', ['--from' => '2026-03-31', '--to' => '2026-03-01'])
        ->assertFailed();

    Queue::assertNothingPushed();
});
