<?php

namespace App\Support;

use App\Models\Premise;

/**
 * Where a premise is and how far from it still counts as being there — the two
 * facts the mobile app needs to draw its location card before an employee
 * punches.
 *
 * A geofence only exists when the premise has coordinates; a radius is optional
 * on top of that. Both absences are legitimate, and both mean the same thing to
 * the client: it never shows the out-of-range state and never blocks a punch.
 * Refusing to record a punch an employee actually made is worse than recording
 * a suspect one (kolvi-mobile decision D-F1-c).
 *
 * The client's own distance check is advisory. The authoritative geofence
 * decision is the server's, at punch time (PRD §6 item 2), and is not made here.
 */
class Geofence
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?int $radiusMeters,
    ) {}

    /**
     * The premise's geofence, or null when it has no coordinates to centre one
     * on — including when the employee is attached to no premise at all.
     */
    public static function fromPremise(?Premise $premise): ?self
    {
        if ($premise === null || $premise->lat === null || $premise->lng === null) {
            return null;
        }

        return new self($premise->lat, $premise->lng, $premise->geofence_radius_meters);
    }
}
