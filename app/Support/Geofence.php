<?php

namespace App\Support;

use App\Enums\GeoStatus;
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
 * decision is the server's, at punch time (PRD §6 item 2), and is
 * {@see self::verdictFor()} below.
 */
class Geofence
{
    /**
     * Mean Earth radius in metres — the sphere {@see self::distanceTo()}
     * assumes, and the same one the mobile client measures against.
     */
    private const EARTH_RADIUS_METERS = 6_371_008.8;

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

    /**
     * The verdict recorded on a punch reported from these coordinates — the
     * server's own reading, never the client's.
     *
     * Three absences all answer `unknown`, and the enum documents why they are
     * one verdict rather than three: no fix, a premise with no coordinates
     * (there is no geofence to pass it), and a premise with no radius (nobody
     * configured one). None of them refuses the punch.
     *
     * The device's reported accuracy is deliberately not folded in. The client
     * lets uncertainty lean its card towards `confirmed`, because a wrong
     * `outside` disables the button in front of an employee standing at their
     * own gate; the register has no such cost to weigh, so it records the
     * distance that was actually measured and stores the accuracy beside it for
     * whoever reviews the mark.
     */
    public static function verdictFor(?self $geofence, ?float $lat, ?float $lng): GeoStatus
    {
        if ($geofence === null || $geofence->radiusMeters === null || $lat === null || $lng === null) {
            return GeoStatus::Unknown;
        }

        return $geofence->distanceTo($lat, $lng) <= $geofence->radiusMeters
            ? GeoStatus::Inside
            : GeoStatus::Outside;
    }

    /**
     * Great-circle metres from the premise to the given point, by the haversine
     * formula.
     *
     * A sphere rather than the WGS-84 ellipsoid: the error is about 0.3%, which
     * over the couple of hundred metres a geofence spans is well under a metre
     * — far inside the accuracy of any phone fix it is compared against.
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $fromLat = deg2rad($this->lat);
        $toLat = deg2rad($lat);
        $deltaLat = deg2rad($lat - $this->lat);
        $deltaLng = deg2rad($lng - $this->lng);

        $a = sin($deltaLat / 2) ** 2
            + cos($fromLat) * cos($toLat) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }
}
