<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\My\WorkdayController;
use App\Http\Resources\WorkdayDetailResource;
use App\Http\Resources\WorkdayResource;
use App\Models\User;
use App\Models\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * The Jornada tab's Historial screen (kolvi-mobile KMO-33) and its day-detail
 * screen (KMO-34): the employee's own computed workdays, mirroring the same
 * query {@see WorkdayController::index} already runs for the web self-service
 * list.
 *
 * Range-queryable rather than a fixed window, per Resolución 38 Art. 22.1 — the
 * client pages back through history a month at a time by moving `from`/`to`
 * itself; nothing here caps how far back it may ask.
 */
class WorkdaysController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $from = $request->date('from') ?? Carbon::today()->startOfMonth();
        $to = $request->date('to') ?? Carbon::today()->endOfMonth();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        $workdays = Workday::query()
            ->where('user_id', $user->id)
            ->with('leave:id,type')
            ->betweenDates($from, $to)
            ->orderByDesc('date')
            ->get();

        return WorkdayResource::collection($workdays);
    }

    /**
     * One workday's detail: the shift window the attendance strip draws its
     * axis from, and each punch's own `mark_id` so the app can retrieve that
     * punch's comprobante through `GET /api/v1/marks/{mark}`.
     *
     * A date with no computed workday row for this employee 404s — reachable
     * only through a date Historial already listed, so a legitimate miss here
     * means the day has not been rolled up yet rather than the client asking
     * for something that was never going to exist.
     */
    public function show(Request $request, string $date): WorkdayDetailResource
    {
        /** @var User $user */
        $user = $request->user();

        $workday = Workday::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->with(['markIn', 'markOut', 'leave:id,type'])
            ->firstOrFail();

        return new WorkdayDetailResource($workday);
    }
}
