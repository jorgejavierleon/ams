<?php

namespace App\Http\Controllers;

use App\Enums\MarkType;
use App\Managers\MarkManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The authenticated landing page. For employees who may clock in/out it surfaces
 * the attendance widget state (today's shift and any punches already made) so
 * registering entry/exit is the first thing they see after logging in.
 */
class DashboardController extends Controller
{
    public function index(Request $request, MarkManager $marks): Response
    {
        $user = $request->user();

        // Gate on the permission the user actually holds — not the super-admin
        // gate — so the widget matches the `permission:` middleware guarding the
        // store route (which the admin gate does not bypass). Admins hold the
        // permission directly via the seeder, so they get the widget too.
        $canClock = $user->getAllPermissions()->pluck('name')->contains('ClockOwn:Mark');

        return Inertia::render('dashboard', [
            'clock' => $canClock
                ? [
                    'shift' => $marks->getShiftForToday($user),
                    'in' => $marks->getTodayMark(MarkType::In, $user)?->date_time->format('H:i'),
                    'out' => $marks->getTodayMark(MarkType::Out, $user)?->date_time->format('H:i'),
                ]
                : null,
        ]);
    }
}
