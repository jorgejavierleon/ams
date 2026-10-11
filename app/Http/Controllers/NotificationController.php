<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the in-app notification bell (KOL-87): a lightweight JSON API,
 * polled from the frontend rather than driven by an Inertia visit, so
 * checking for new report-export notifications never re-runs whatever
 * (possibly heavy, e.g. a report) controller logic backs the current page.
 *
 * Every lookup goes through the authenticated notifiable's own
 * `notifications()` relation, which is keyed to that user's id — this is
 * what keeps a user from ever seeing, or marking read, another user's
 * notifications (KOL-87 AC #5).
 */
class NotificationController extends Controller
{
    /**
     * The most recent notifications for the bell dropdown, plus the unread
     * count for the badge.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(20)->get();

        return response()->json([
            'notifications' => $notifications->map(fn ($notification): array => [
                'id' => $notification->id,
                'data' => $notification->data,
                'readAt' => $notification->read_at?->toIso8601String(),
                'createdAt' => $notification->created_at?->toIso8601String(),
            ]),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification read (KOL-87 AC #4). Scoped through the
     * authenticated user's own relation, so a 404 is the only possible
     * outcome for someone else's notification id.
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['read' => true]);
    }
}
