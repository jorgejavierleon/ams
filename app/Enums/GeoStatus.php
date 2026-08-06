<?php

namespace App\Enums;

/**
 * Where a punch was made relative to the premise it belongs to, as the server
 * decided it at punch time (PRD §6 item 2).
 *
 * The client sends its own reading with the punch, and that reading is advisory
 * — a phone that has been in a lift for thirty seconds is wrong about where it
 * is. The value stored on the mark is always the server's own evaluation.
 *
 * `Unknown` is a first-class verdict rather than a failure, and covers three
 * different absences that mean the same thing legally: no fix (the employee
 * denied location permission), a premise with no coordinates, and a premise
 * with no configured radius. None of them may block a punch — refusing to
 * record attendance an employee actually made is a legal problem, not a product
 * one (kolvi-mobile decision D-F1-c, PRD §283).
 */
enum GeoStatus: string
{
    case Inside = 'inside';
    case Outside = 'outside';
    case Unknown = 'unknown';
}
