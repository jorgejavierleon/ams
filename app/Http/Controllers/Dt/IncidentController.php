<?php

namespace App\Http\Controllers\Dt;

use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only list of technical incidents for the Labor Department (DT) audit.
 *
 * Incidents are the outages of the electronic attendance system logged by the
 * employer. Inspectors review them during a Resolución 38 audit, so the list is
 * constrained to the audit session organization by {@see OrganizationScope}
 * and offers a date-range filter but no create/edit/delete.
 */
class IncidentController extends Controller
{
    use ResolvesTableSort;

    /**
     * List the audited employer's incidents, optionally narrowed to a date range.
     */
    public function index(Request $request): Response
    {
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['start_time', 'end_time', 'description'],
            'start_time',
            'desc',
        );

        $from = $request->date('from');
        $to = $request->date('to');

        $incidents = Incident::query()
            ->when($from, fn ($query) => $query->whereDate('start_time', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('start_time', '<=', $to))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dt/incidents/index', [
            'incidents' => $incidents->through(fn (Incident $incident) => [
                'id' => $incident->id,
                'start_time' => $incident->start_time->format('Y-m-d H:i'),
                'end_time' => $incident->end_time?->format('Y-m-d H:i'),
                'duration' => $incident->duration,
                'description' => $incident->description,
            ]),
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
            ],
        ]);
    }
}
