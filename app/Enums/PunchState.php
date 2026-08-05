<?php

namespace App\Enums;

/**
 * Where an employee is in their working day, as the mobile home screen reports
 * it: `before` → `working` → `done`.
 *
 * Three states and no more. Colación was dropped as a punch type for v1 (mobile
 * decision D-F1-a) and the one-IN-one-OUT-per-day guard was kept (D-F1-b), so
 * the PRD's older five-state table — which had `break` and `afterbreak` in it —
 * is superseded. The app treats any other value as unknown and draws no status
 * line at all: telling an employee who punched in at 08:00 that they have not
 * marked entrada is the one wrong answer on that screen that costs them a
 * workday.
 */
enum PunchState: string
{
    case Before = 'before';
    case Working = 'working';
    case Done = 'done';

    /**
     * Derive the state from the punches the employee has already registered
     * today. An OUT without an IN still reads as `done`: the day is over as far
     * as the punch button is concerned, and the missing entrada is a correction
     * an admin opens, not something the home screen invites a second punch for.
     */
    public static function fromTodaysMarks(bool $hasMarkIn, bool $hasMarkOut): self
    {
        if ($hasMarkOut) {
            return self::Done;
        }

        return $hasMarkIn ? self::Working : self::Before;
    }
}
