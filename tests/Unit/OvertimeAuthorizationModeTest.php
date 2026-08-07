<?php

use App\Enums\OvertimeAuthorizationMode;

test('pre-authorization allows requests only', function () {
    expect(OvertimeAuthorizationMode::PreAuthorization->allowsRequests())->toBeTrue()
        ->and(OvertimeAuthorizationMode::PreAuthorization->allowsShiftExcess())->toBeFalse();
});

test('post-hoc allows shift excess only', function () {
    expect(OvertimeAuthorizationMode::PostHoc->allowsRequests())->toBeFalse()
        ->and(OvertimeAuthorizationMode::PostHoc->allowsShiftExcess())->toBeTrue();
});

test('combined allows both flows, so neither has to special-case it', function () {
    expect(OvertimeAuthorizationMode::Combined->allowsRequests())->toBeTrue()
        ->and(OvertimeAuthorizationMode::Combined->allowsShiftExcess())->toBeTrue();
});
