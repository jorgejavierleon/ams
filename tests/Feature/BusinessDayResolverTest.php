<?php

use App\Models\Holiday;
use App\Services\BusinessDayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = new BusinessDayResolver;
    // A fixed Monday to anchor weekday arithmetic deterministically.
    $this->monday = Carbon::parse('2026-07-13')->startOfWeek();
});

test('weekdays without holidays are business days', function () {
    expect($this->resolver->isBusinessDay($this->monday))->toBeTrue();
});

test('weekends are not business days', function () {
    $saturday = $this->monday->copy()->addDays(5);
    $sunday = $this->monday->copy()->addDays(6);

    expect($this->resolver->isBusinessDay($saturday))->toBeFalse()
        ->and($this->resolver->isBusinessDay($sunday))->toBeFalse();
});

test('holidays are not business days', function () {
    $wednesday = $this->monday->copy()->addDays(2);
    Holiday::factory()->create(['date' => $wednesday->toDateString()]);

    expect($this->resolver->isBusinessDay($wednesday))->toBeFalse();
});

test('nextBusinessDay skips the weekend', function () {
    $friday = $this->monday->copy()->addDays(4);

    expect($this->resolver->nextBusinessDay($friday)->toDateString())
        ->toBe($this->monday->copy()->addWeek()->toDateString());
});

test('nextBusinessDay skips a holiday', function () {
    $wednesday = $this->monday->copy()->addDays(2);
    Holiday::factory()->create(['date' => $wednesday->toDateString()]);

    // From Tuesday, the next business day jumps over the Wednesday holiday.
    $tuesday = $this->monday->copy()->addDay();

    expect($this->resolver->nextBusinessDay($tuesday)->toDateString())
        ->toBe($this->monday->copy()->addDays(3)->toDateString());
});

test('a correction is not allowed on the same day', function () {
    expect($this->resolver->correctionAllowed($this->monday, $this->monday))->toBeFalse();
});

test('a correction is allowed from the next business day onwards', function () {
    $tuesday = $this->monday->copy()->addDay();

    expect($this->resolver->correctionAllowed($this->monday, $tuesday))->toBeTrue();
});
