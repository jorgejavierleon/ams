<?php

namespace App\Http\Controllers\Api;

use App\Actions\FileQueuedPunchAsAddition;
use App\Enums\GeoStatus;
use App\Enums\MarkType;
use App\Http\Controllers\Controller;
use App\Http\Resources\MarkResource;
use App\Managers\MarkManager;
use App\Models\Mark;
use App\Models\User;
use App\Services\TimeZoneService;
use App\Support\Geofence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
    public function __construct(
        private readonly TimeZoneService $timeZoneService,
        private readonly FileQueuedPunchAsAddition $fileQueuedPunch,
    ) {}

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
     * **Online, the request carries no timestamp** and one is rejected rather
     * than ignored. Resolución 38 Art. 11 makes the register the legal record of
     * when someone worked, and a time the device chooses is a time the device can
     * falsify — so the server stamps it, in the employee's own timezone
     * (kolvi-mobile design decision "§5 F1 — Timestamps").
     *
     * **Queued, it carries `device_datetime`** — and that reverses nothing above.
     * Art. 10 expressly permits a device with no connection to `capturar y
     * almacenar la correspondiente marca` and send it on when the signal returns,
     * and Art. 11 hangs off that exception: the sello de tiempo is the hour the
     * marcación *is made*. Stamping the server's clock on a punch made four hours
     * earlier in a basement would register a false hour, against Art. 11, Art. 44
     * and Art. 41 b). So the server still assigns `date_time` on both paths; on
     * the queued one it **adjudicates** it from the device reading — a bounded
     * window, an explicit refusal, and the raw reading kept beside the legal
     * value permanently (kolvi-mobile design decision §4).
     *
     * The four location keys are all nullable: an explicit `null` is the app
     * reporting that it had no fix, which is a punch that must still be
     * recorded.
     */
    public function store(Request $request, MarkManager $marks): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['in', 'out', 'IN', 'OUT'])],
            // Prohibited rather than ignored, on both paths: a client still
            // sending one should find out immediately instead of believing it
            // chose the time. A queued punch sends `device_datetime`, which is
            // evidence the server judges, not an instruction it obeys.
            'datetime' => ['prohibited'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            // Accepted, then ignored when the verdict is decided: the client's
            // own reading is advisory (PRD §6 item 2). It travels so the two
            // readings can be compared later, never so it can settle one.
            'geo_status' => ['nullable', Rule::enum(GeoStatus::class)],
            // The offline pair, present only on a queued punch and only
            // together. Half a pair is a client bug, not a punch: a reading with
            // no key cannot be de-duplicated on retry, and a key with no reading
            // is a punch claiming to be queued without saying when it happened.
            'device_datetime' => ['nullable', 'required_with:idempotency_key', 'date_format:Y-m-d H:i:s'],
            'idempotency_key' => ['nullable', 'required_with:device_datetime', 'uuid:4'],
        ]);

        $type = MarkType::from(strtolower($validated['type']));
        /** @var User $user */
        $user = $request->user();
        $idempotencyKey = $validated['idempotency_key'] ?? null;

        // A retry whose answer was lost is not a second punch, and the register
        // is what says so rather than the client guessing. Answered before
        // anything else is judged: a punch already recorded is recorded, whatever
        // the queue has since become old enough for.
        if ($idempotencyKey !== null) {
            $recorded = Mark::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($recorded instanceof Mark) {
                // 200 rather than 201, with the receipt the original request
                // returned, byte for byte — the client drops the punch from its
                // queue and the employee never learns the difference.
                return (new MarkResource($recorded))->response()->setStatusCode(200);
            }
        }

        $deviceDateTime = $this->deviceDateTime($validated['device_datetime'] ?? null, $user);

        if ($deviceDateTime !== null) {
            $refusal = $this->refuseOutOfWindow($deviceDateTime, $type, $user);

            if ($refusal !== null) {
                return $refusal;
            }
        }

        // One IN and one OUT per day (decision D-F1-b) — the same rule
        // TodayController derives before|working|done from, so the read and the
        // write sides of the punch agree. 409 and nothing else: the mobile
        // client keys on the status to render this as a calm state rather than
        // as a failed punch. A queued punch is guarded against the day it was
        // made, which is not necessarily today.
        $collision = $deviceDateTime === null
            ? $marks->getTodayMark($type, $user)
            : $marks->getMarkOnDate($type, $deviceDateTime, $user);

        if ($collision !== null) {
            abort(409, __('ui.marks.api.already_marked.'.$type->value));
        }

        $mark = $marks->createMark(
            $type,
            $user,
            $deviceDateTime?->format('Y-m-d H:i:s'),
            // Provenance has to be present at creation rather than attached
            // afterwards the way geolocation is: MarkObserver hashes it into the
            // Art. 8 checksum, so clearing the flag later invalidates the mark.
            $deviceDateTime === null ? [] : [
                'device_datetime' => $deviceDateTime,
                'synced_at' => $this->now($user),
                'captured_offline' => true,
                'idempotency_key' => $idempotencyKey,
            ],
        );

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
     * The device's raw reading as a Carbon in the employee's own timezone, so it
     * compares against their wall clock rather than the server's. Null on an
     * online punch, which sends none.
     */
    private function deviceDateTime(?string $value, User $user): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value, $this->timezone($user));
    }

    /**
     * The refusal a queued punch outside the accepted window earns, or null when
     * it is inside it. Both answers are a 422 with a Spanish message the app
     * shows verbatim, and neither records a mark.
     */
    private function refuseOutOfWindow(CarbonInterface $deviceDateTime, MarkType $type, User $user): ?JsonResponse
    {
        $now = $this->now($user);

        // A punch cannot have been made in a future the register has not reached
        // (Art. 11). The tolerance is for ordinary clock drift and nothing more —
        // there is no addition pathway here, because there is no missing mark to
        // add: the employee's phone is simply wrong about what time it is, and
        // the fix is on the device.
        if ($deviceDateTime->greaterThan($now->copy()->addMinutes((int) config('ams.offline_punch_future_tolerance_minutes')))) {
            return $this->refusal('queued_punch_in_future', __('ui.marks.api.offline.in_future'));
        }

        $maxAgeHours = (int) config('ams.offline_punch_max_age_hours');

        if ($deviceDateTime->greaterThanOrEqualTo($now->copy()->subHours($maxAgeHours))) {
            return null;
        }

        // Neither inserted nor discarded: the employee's evidence enters the
        // record through the Art. 39 b) addition, bilaterally and flagged.
        $this->fileQueuedPunch->handle($user, $type, $deviceDateTime);

        return $this->refusal('queued_punch_too_old', __('ui.marks.api.offline.too_old', [
            'hours' => $maxAgeHours,
            'review_hours' => (int) config('ams.mark_modification_timeout_hours'),
        ]));
    }

    /**
     * A 422 the mobile client can both branch on and read out loud: `code` tells
     * it which state to enter, `message` is the Spanish it shows unchanged.
     */
    private function refusal(string $code, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], 422);
    }

    /**
     * Now, on the employee's own wall clock — the same naive reading marks are
     * stored in, so it can be compared against a device reading directly.
     */
    private function now(User $user): CarbonInterface
    {
        return Carbon::now($this->timezone($user));
    }

    private function timezone(User $user): string
    {
        return $this->timeZoneService->getUserTimezone($user);
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
