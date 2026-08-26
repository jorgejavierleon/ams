<?php

use App\Enums\ReportPeriodType;
use App\Support\ReportPeriod;

test('a full month resolves to the first and last calendar day', function () {
    $period = new ReportPeriod(2026, 2, ReportPeriodType::Month);

    expect($period->start()->toDateString())->toBe('2026-02-01')
        ->and($period->end()->toDateString())->toBe('2026-02-28');
});

test('a full month resolves to the last day of a leap february', function () {
    $period = new ReportPeriod(2028, 2, ReportPeriodType::Month);

    expect($period->end()->toDateString())->toBe('2028-02-29');
});

test('the first quincena spans day 1 to day 15 regardless of month length', function () {
    $period = new ReportPeriod(2026, 4, ReportPeriodType::FirstFortnight);

    expect($period->start()->toDateString())->toBe('2026-04-01')
        ->and($period->end()->toDateString())->toBe('2026-04-15');
});

test('the second quincena spans day 16 to the last day of a 31-day month', function () {
    $period = new ReportPeriod(2026, 1, ReportPeriodType::SecondFortnight);

    expect($period->start()->toDateString())->toBe('2026-01-16')
        ->and($period->end()->toDateString())->toBe('2026-01-31');
});

test('the second quincena spans day 16 to the last day of a 30-day month', function () {
    $period = new ReportPeriod(2026, 4, ReportPeriodType::SecondFortnight);

    expect($period->start()->toDateString())->toBe('2026-04-16')
        ->and($period->end()->toDateString())->toBe('2026-04-30');
});

test('an invalid month is rejected', function () {
    new ReportPeriod(2026, 13, ReportPeriodType::Month);
})->throws(InvalidArgumentException::class);
