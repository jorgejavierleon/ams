<?php

namespace App\Services\Overtime;

use App\Exceptions\OvertimeDecisionRefused;

/**
 * Which of the four legal ceilings a proposed approval would exceed (PRD
 * §7.3, Código del Trabajo art. 31): the daily and weekly overtime caps, and
 * the daily and weekly ceilings on ordinary-plus-extraordinary combined.
 *
 * A value object rather than a single boolean so the justification a reviewer
 * is asked for ({@see OvertimeDecisionRefused::withoutJustification()})
 * can name exactly which ceiling their approval crosses.
 */
final readonly class OvertimeCapBreach
{
    public function __construct(
        public bool $dailyOvertime,
        public bool $weeklyOvertime,
        public bool $dailyTotal,
        public bool $weeklyTotal,
    ) {}

    /**
     * Whether the proposed approval exceeds any ceiling at all - the
     * condition that turns the justification from optional to mandatory.
     */
    public function any(): bool
    {
        return $this->dailyOvertime || $this->weeklyOvertime || $this->dailyTotal || $this->weeklyTotal;
    }

    /**
     * The breached ceilings, named for a human reading the refusal.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return array_values(array_filter([
            $this->dailyOvertime ? 'daily overtime cap (Código del Trabajo art. 31)' : null,
            $this->weeklyOvertime ? 'weekly overtime cap' : null,
            $this->dailyTotal ? 'combined ordinary-plus-extraordinary daily ceiling' : null,
            $this->weeklyTotal ? 'combined ordinary-plus-extraordinary weekly ceiling' : null,
        ]));
    }
}
