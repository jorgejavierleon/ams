<?php

namespace App\Http\Controllers\My;

use App\Enums\MarkType;
use App\Http\Controllers\Controller;
use App\Managers\MarkManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Employee self-service attendance: the authenticated employee registers their
 * own entry (IN) and exit (OUT) punches from the dashboard. Capability is gated
 * by the `ClockOwn:Mark` permission on the route.
 */
class MarkController extends Controller
{
    public function store(Request $request, MarkManager $marks): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(MarkType::class)],
        ]);

        $type = MarkType::from($data['type']);
        $user = $request->user();

        // One punch of each type per day: block a second identical mark.
        if ($marks->getTodayMark($type, $user) !== null) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('ui.marks.flash.already_marked'),
            ]);

            return back();
        }

        $marks->createMark($type, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.marks.flash.registered'),
        ]);

        return back();
    }
}
