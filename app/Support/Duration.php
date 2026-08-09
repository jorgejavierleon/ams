<?php

namespace App\Support;

use App\Models\OvertimeAuthorization;
use InvalidArgumentException;

/**
 * A span of time held in whole seconds and rendered as `HH:MM:SS`.
 *
 * The overtime figures are stored as `time` columns and compared, subtracted
 * and minimised against each other constantly — what was authorised against
 * what was calculated, what is payable against what is not. Doing that on the
 * strings invites the two mistakes this type exists to prevent: comparing
 * `'10:00:00'` against `'9:00:00'` lexically and getting the wrong answer, and
 * rounding somewhere in the middle, which Resolución 38 art. 44 does not allow.
 *
 * Null is not a zero here — {@see self::tryFrom()} preserves the distinction, so
 * a tenant with no request flow keeps a null OHR rather than a zero that would
 * floor every payable figure to nothing.
 *
 * @see OvertimeAuthorization
 */
final readonly class Duration
{
    private function __construct(public int $seconds) {}

    /**
     * Build from whole seconds. A negative span is not a concept the overtime
     * figures have: fewer hours than none is none.
     */
    public static function fromSeconds(int $seconds): self
    {
        return new self(max(0, $seconds));
    }

    /**
     * Build from an `HH:MM:SS` string, as the `time` columns store it. Hours are
     * not capped at 24 — a span is not a clock reading.
     */
    public static function fromTimeString(string $time): self
    {
        if (preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/', $time, $parts) !== 1) {
            throw new InvalidArgumentException("Not an HH:MM:SS duration: [{$time}].");
        }

        return new self(((int) $parts[1] * 3600) + ((int) $parts[2] * 60) + (int) $parts[3]);
    }

    /**
     * The same as {@see self::fromTimeString()}, but passing null through: a
     * figure the tenant never produces stays absent instead of becoming zero.
     */
    public static function tryFrom(?string $time): ?self
    {
        return $time === null ? null : self::fromTimeString($time);
    }

    /** A span of no time at all. */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * The shortest of the given spans, ignoring the absent ones. Returns null
     * only when every input is absent — the MIN rule of PRD §7.1 compares the
     * figures that exist, never the ones that do not.
     */
    public static function min(?self ...$durations): ?self
    {
        $present = array_filter($durations);

        return $present === []
            ? null
            : array_reduce(
                $present,
                fn (?self $carry, self $duration): self => $carry === null || $duration->seconds < $carry->seconds
                    ? $duration
                    : $carry,
                null,
            );
    }

    /**
     * This span less another, clamped at zero.
     */
    public function minus(?self $other): self
    {
        return self::fromSeconds($this->seconds - ($other->seconds ?? 0));
    }

    public function isZero(): bool
    {
        return $this->seconds === 0;
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $this->seconds === $other->seconds;
    }

    /**
     * `HH:MM:SS`, built by hand rather than through `gmdate()` so a span of a
     * day or more reads as the hours it is instead of wrapping back to zero.
     */
    public function toTimeString(): string
    {
        return sprintf(
            '%02d:%02d:%02d',
            intdiv($this->seconds, 3600),
            intdiv($this->seconds % 3600, 60),
            $this->seconds % 60,
        );
    }

    public function __toString(): string
    {
        return $this->toTimeString();
    }
}
