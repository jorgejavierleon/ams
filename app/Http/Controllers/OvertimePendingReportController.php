<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Models\Workday;
use App\Services\Overtime\OvertimePendingReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The stale-overtime alert report of PRD §12 (KOL-52): every day whose shift
 * excess has gone unapproved past the organization's configured threshold,
 * with the responsible supervisor identifiable alongside the employee, and
 * the average resolution time surfaced from the same underlying data.
 */
class OvertimePendingReportController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request, OvertimePendingReport $report): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['date'],
            'date',
            'asc',
        );

        $threshold = $report->thresholdDays();

        $stale = $report->staleWorkdaysQuery($threshold)
            ->with(['user:id,name,supervisor_id', 'user.supervisor:id,name'])
            ->when($search, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('name', 'like', "%{$search}%"),
            ))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('overtime/pending/index', [
            'records' => $stale->through(fn (Workday $workday) => [
                'id' => $workday->id,
                'employee' => $workday->user?->name,
                'supervisor' => $workday->user?->supervisor?->name,
                'date' => $workday->date->format('Y-m-d'),
                'days_pending' => $workday->date->diffInDays(Carbon::today(), absolute: true),
                'calculated_hours' => $workday->calculated_overtime,
                'workday_url' => route('workdays.show', $workday),
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
            'stats' => [
                'threshold_days' => $threshold,
                // The paginator's own total, so this always agrees with what
                // the table below is actually showing — including under a
                // search filter — rather than a second, unfiltered count.
                'stale_count' => $stale->total(),
                'average_resolution_days' => $report->averageResolutionDays(),
            ],
        ]);
    }
}
