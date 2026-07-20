<?php

namespace App\Services\Reports;

use App\Models\Incident;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Support\Carbon;

/**
 * Builds the "Reporte de incidentes técnicos" required by Resolución 38,
 * Art. 27 f): the log of incidents that caused the electronic attendance system
 * to cease operating in whole or in part, giving traceability between the use of
 * the contingency mechanism and the recorded outages.
 *
 * Unlike the attendance/journey reports, this one is not per-worker (Art. 24 d)
 * excludes it from the worker-search screen): it is a per-employer log. The
 * article prescribes, "al menos", three columns — start date/time, end date/time
 * and description — to which we add the computed outage duration as a permitted
 * extra. Rows are constrained to the audit session organization by the
 * {@see OrganizationScope} global scope on {@see Incident}.
 */
class IncidentsReportService
{
    /**
     * List the audited employer's incidents whose outage began within the range,
     * most recent first.
     *
     * @return list<array{
     *     start_time: string,
     *     end_time: string|null,
     *     duration: string|null,
     *     description: string,
     * }>
     */
    public function build(Carbon $start, Carbon $end): array
    {
        return Incident::query()
            ->whereDate('start_time', '>=', $start)
            ->whereDate('start_time', '<=', $end)
            ->orderBy('start_time', 'desc')
            ->get()
            ->map(fn (Incident $incident): array => [
                'start_time' => $incident->start_time->format('Y-m-d H:i'),
                'end_time' => $incident->end_time?->format('Y-m-d H:i'),
                'duration' => $incident->duration,
                'description' => $incident->description,
            ])
            ->all();
    }
}
