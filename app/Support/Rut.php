<?php

namespace App\Support;

/**
 * Self-contained helper for Chilean RUT/RUN numbers.
 *
 * Handles the three things the application needs without pulling in an
 * external package: normalisation to a canonical `body-dv` form, display
 * formatting with thousands separators, and modulo-11 validation of the
 * verifier digit.
 */
final class Rut
{
    /**
     * Reduce any user input to its digits and verifier ("123456785", "12.345.678-5").
     *
     * @return array{body: string, dv: string}|null Null when the value has no verifier.
     */
    private static function split(string $value): ?array
    {
        $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $value) ?? '');

        if (strlen($clean) < 2) {
            return null;
        }

        return [
            'body' => substr($clean, 0, -1),
            'dv' => substr($clean, -1),
        ];
    }

    /**
     * Compute the expected verifier digit for a numeric body.
     */
    public static function computeDv(string $body): string
    {
        $sum = 0;
        $multiplier = 2;

        foreach (array_reverse(str_split($body)) as $digit) {
            $sum += ((int) $digit) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);

        return match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };
    }

    /**
     * Validate that the value is a well-formed RUT with a correct verifier digit.
     */
    public static function isValid(string $value): bool
    {
        $parts = self::split($value);

        if ($parts === null || ! ctype_digit($parts['body']) || (int) $parts['body'] === 0) {
            return false;
        }

        return self::computeDv($parts['body']) === $parts['dv'];
    }

    /**
     * Canonical storage form: `12345678-5` (no dots, upper-case K, single dash).
     */
    public static function normalize(string $value): string
    {
        $parts = self::split($value);

        if ($parts === null) {
            return $value;
        }

        return $parts['body'].'-'.$parts['dv'];
    }

    /**
     * Human display form: `12.345.678-5`.
     */
    public static function format(string $value): string
    {
        $parts = self::split($value);

        if ($parts === null) {
            return $value;
        }

        return number_format((int) $parts['body'], 0, '', '.').'-'.$parts['dv'];
    }
}
