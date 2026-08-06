<?php

namespace App\Http\Controllers\Api;

use App\Enums\GeoStatus;
use App\Enums\MarkType;
use App\Http\Controllers\Controller;
use App\Http\Resources\MarkResource;
use App\Managers\MarkManager;
use App\Models\Mark;
use App\Support\Geofence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The Sanctum-authenticated attendance API for the employee mobile app. Punches
 * are created through {@see MarkManager} so the same legal snapshot, checksum
 * and shift logic as the web app apply (Resolución 38); this controller only
 * validates the request and shapes the response.
 */
class MarkController extends Controller
{
    /**
     * The authenticated employee's most recent punches.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $marks = Mark::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('date_time')
            ->limit(10)
            ->get();

        return MarkResource::collection($marks);
    }

    /**
     * Register a punch for the authenticated employee from their mobile device.
     *
     * The request carries no timestamp, and one is rejected rather than ignored.
     * Resolución 38 Art. 11 makes the register the legal record of when someone
     * worked, and a time the device chooses is a time the device can falsify —
     * so the server stamps it, in the employee's own timezone (kolvi-mobile
     * design decision "§5 F1 — Timestamps").
     *
     * The four location keys are all nullable: an explicit `null` is the app
     * reporting that it had no fix, which is a punch that must still be
     * recorded.
     */
    public function store(Request $request, MarkManager $marks): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['in', 'out', 'IN', 'OUT'])],
            // Prohibited rather than ignored: a client still sending one should
            // find out immediately instead of believing it chose the time.
            'datetime' => ['prohibited'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            // Accepted, then ignored when the verdict is decided: the client's
            // own reading is advisory (PRD §6 item 2). It travels so the two
            // readings can be compared later, never so it can settle one.
            'geo_status' => ['nullable', Rule::enum(GeoStatus::class)],
        ]);

        $type = MarkType::from(strtolower($validated['type']));
        $user = $request->user();

        // One IN and one OUT per day (decision D-F1-b) — the same rule
        // TodayController derives before|working|done from, so the read and the
        // write sides of the punch agree. 409 and nothing else: the mobile
        // client keys on the status to render this as a calm state rather than
        // as a failed punch.
        if ($marks->getTodayMark($type, $user) !== null) {
            abort(409, __('ui.marks.api.already_marked.'.$type->value));
        }

        $mark = $marks->createMark($type, $user);

        $lat = $this->coordinate($validated['lat'] ?? null);
        $lng = $this->coordinate($validated['lng'] ?? null);

        // Geolocation is metadata and not part of the integrity checksum, so it
        // is attached after the mark is stamped rather than by reaching into
        // MarkManager. The verdict is measured against the premise snapshotted
        // onto the mark, and never blocks the punch: an out-of-range or unknown
        // punch is recorded, flagged and still answers 201 (decision D-F1-c).
        $mark->update([
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_meters' => $validated['accuracy_m'] ?? null,
            'geo_status' => Geofence::verdictFor(Geofence::fromPremise($mark->premise), $lat, $lng),
        ]);

        return (new MarkResource($mark))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * A validated coordinate as a float. It arrives as a JSON number, but a
     * numeric string is equally valid to the `numeric` rule and would reach the
     * haversine as a string.
     */
    private function coordinate(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * A single punch belonging to the authenticated employee.
     */
    public function show(Request $request, Mark $mark): MarkResource
    {
        abort_unless($mark->user_id === $request->user()->id, 404);

        return new MarkResource($mark);
    }
}
