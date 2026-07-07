<?php

use App\Support\Rut;

test('computes the verifier digit', function () {
    expect(Rut::computeDv('12345678'))->toBe('5');
    expect(Rut::computeDv('11111111'))->toBe('1');
    expect(Rut::computeDv('10000013'))->toBe('K');
});

test('validates well-formed ruts regardless of formatting', function () {
    expect(Rut::isValid('12.345.678-5'))->toBeTrue();
    expect(Rut::isValid('12345678-5'))->toBeTrue();
    expect(Rut::isValid('123456785'))->toBeTrue();
    expect(Rut::isValid('10.000.013-K'))->toBeTrue();
    expect(Rut::isValid('10.000.013-k'))->toBeTrue();
});

test('rejects ruts with a wrong verifier digit', function () {
    expect(Rut::isValid('12.345.678-9'))->toBeFalse();
    expect(Rut::isValid('11111111-2'))->toBeFalse();
});

test('rejects malformed input', function () {
    expect(Rut::isValid(''))->toBeFalse();
    expect(Rut::isValid('abc'))->toBeFalse();
    expect(Rut::isValid('0-0'))->toBeFalse();
});

test('normalizes to a canonical body-dv form', function () {
    expect(Rut::normalize('12.345.678-5'))->toBe('12345678-5');
    expect(Rut::normalize('10.000.013-k'))->toBe('10000013-K');
});

test('formats with thousands separators for display', function () {
    expect(Rut::format('12345678-5'))->toBe('12.345.678-5');
    expect(Rut::format('10000013-K'))->toBe('10.000.013-K');
});
