<?php

use App\Services\Overtime\OvertimeExcessPolicy;
use App\Services\Overtime\ShiftExcess;

/**
 * The excess arithmetic on its own, away from the database, so the cases that
 * are awkward to stage as real workdays — a shift running past midnight whose
 * exit mark falls on the following calendar day — still get exercised directly.
 */
function excess(
    ?string $markIn,
    ?string $markOut,
    ?string $shiftStart = '08:00:00',
    ?string $shiftEnd = '17:00:00',
    string $date = '2026-08-10',
): ?ShiftExcess {
    return ShiftExcess::for(
        markIn: $markIn,
        markOut: $markOut,
        shiftStartTime: $shiftStart,
        shiftEndTime: $shiftEnd,
        date: $date,
    );
}

test('a day worked exactly to the shift has no excess on either side', function () {
    $excess = excess('2026-08-10 08:00:00', '2026-08-10 17:00:00');

    expect($excess->preShiftExcess())->toBe('00:00:00')
        ->and($excess->postShiftExcess())->toBe('00:00:00');
});

test('staying past the shift end is a post-shift excess', function () {
    $excess = excess('2026-08-10 08:00:00', '2026-08-10 18:30:00');

    expect($excess->postShiftExcess())->toBe('01:30:00')
        ->and($excess->preShiftExcess())->toBe('00:00:00');
});

test('arriving early is a pre-shift excess and never a negative one', function () {
    $excess = excess('2026-08-10 06:45:00', '2026-08-10 17:00:00');

    expect($excess->preShiftExcess())->toBe('01:15:00')
        ->and($excess->postShiftExcess())->toBe('00:00:00');
});

test('arriving late produces no pre-shift excess rather than a negative one', function () {
    $excess = excess('2026-08-10 09:20:00', '2026-08-10 16:00:00');

    expect($excess->preShiftExcess())->toBe('00:00:00')
        ->and($excess->postShiftExcess())->toBe('00:00:00');
});

test('neither excess is the worked span minus the scheduled duration', function () {
    // Two hours early, one hour late: the span exceeds the nine-hour schedule by
    // three hours, but the two excesses are separate figures and stay separate.
    $excess = excess('2026-08-10 06:00:00', '2026-08-10 18:00:00');

    expect($excess->preShiftExcess())->toBe('02:00:00')
        ->and($excess->postShiftExcess())->toBe('01:00:00');
});

test('excess is kept to the second with no rounding', function () {
    $excess = excess('2026-08-10 07:59:13', '2026-08-10 17:00:47');

    expect($excess->preShiftExcess())->toBe('00:00:47')
        ->and($excess->postShiftExcess())->toBe('00:00:47');
});

test('a shift running past midnight measures its excesses against the next day', function () {
    // 22:00–06:00 starting on the 10th: the exit at 06:40 on the 11th is forty
    // minutes past the shift end, not the eighteen hours a clock-time
    // subtraction of 06:40 − 06:00 against the same day would report.
    $excess = excess(
        markIn: '2026-08-10 21:30:00',
        markOut: '2026-08-11 06:40:00',
        shiftStart: '22:00:00',
        shiftEnd: '06:00:00',
    );

    expect($excess->preShiftExcess())->toBe('00:30:00')
        ->and($excess->postShiftExcess())->toBe('00:40:00');
});

test('an overnight shift left before its end has no post-shift excess', function () {
    // Leaving at 23:30 on the starting day is early, not seventeen hours late.
    $excess = excess(
        markIn: '2026-08-10 22:00:00',
        markOut: '2026-08-10 23:30:00',
        shiftStart: '22:00:00',
        shiftEnd: '06:00:00',
    );

    expect($excess->postShiftExcess())->toBe('00:00:00')
        ->and($excess->preShiftExcess())->toBe('00:00:00');
});

test('an excess of more than a day reads as the hours it is', function () {
    $excess = excess('2026-08-10 08:00:00', '2026-08-11 20:00:00');

    expect($excess->postShiftExcess())->toBe('27:00:00');
});

test('a day with no shift is not computed at all', function () {
    expect(excess('2026-08-10 09:00:00', '2026-08-10 18:00:00', shiftStart: null, shiftEnd: null))
        ->toBeNull();
});

test('a day with a single mark is not computed at all', function () {
    expect(excess('2026-08-10 08:00:00', null))->toBeNull()
        ->and(excess(null, '2026-08-10 17:00:00'))->toBeNull();
});

test('calculated overtime is the post-shift excess alone under the default policy', function () {
    $excess = excess('2026-08-10 06:00:00', '2026-08-10 18:00:00');

    expect($excess->calculatedOvertime(OvertimeExcessPolicy::postShiftOnly()))->toBe('01:00:00');
});

test('calculated overtime adds the pre-shift excess when the organization counts it', function () {
    $excess = excess('2026-08-10 06:00:00', '2026-08-10 18:00:00');

    expect($excess->calculatedOvertime(new OvertimeExcessPolicy(countsPreShiftExcess: true)))
        ->toBe('03:00:00');
});
