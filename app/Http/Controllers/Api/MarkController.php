<?php

namespace App\Http\Controllers\Api;

use App\Enums\MarkType;
use App\Http\Controllers\Controller;
use App\Http\Resources\MarkResource;
use App\Managers\MarkManager;
use App\Models\Mark;
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
     */
    public function store(Request $request, MarkManager $marks): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['in', 'out', 'IN', 'OUT'])],
            'datetime' => ['required', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $type = MarkType::from(strtolower($validated['type']));

        $mark = $marks->createMark($type, $request->user(), $validated['datetime']);

        // Geolocation is optional metadata and not part of the integrity
        // checksum, so it is attached after the mark is stamped rather than by
        // reaching into MarkManager.
        if (isset($validated['lat'], $validated['lng'])) {
            $mark->update(['lat' => $validated['lat'], 'lng' => $validated['lng']]);
        }

        return (new MarkResource($mark))
            ->response()
            ->setStatusCode(201);
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
