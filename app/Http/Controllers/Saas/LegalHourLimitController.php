<?php

namespace App\Http\Controllers\Saas;

use App\Actions\CorrectLegalHourLimit;
use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Models\LegalHourLimit;
use App\Services\LegalHourLimits;
use App\Services\LegalHourLimitVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maintenance of the global legal working-hour limits, for Kolvi staff only.
 *
 * These versions are not per-tenant: one row set is what every employer in the
 * system is measured against, so appending one from here silently changes the
 * payable overtime figures of the whole customer base from its effective date.
 * The screen therefore states that effect before saving, never offers an edit
 * field on a version already applied to a calculated day, and attributes every
 * write to a staff user in the audit log.
 *
 * There is deliberately no destroy action. A version a day was judged against
 * has to stay readable for that day to remain explicable, so {@see LegalHourLimit}
 * refuses deletion outright and no route reaches for it.
 */
class LegalHourLimitController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request, LegalHourLimits $limits): Response
    {
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['effective_from', 'ordinary_weekly_hours', 'legal_reference'],
            'effective_from',
            'desc',
        );

        $today = Carbon::today();
        $inForceId = $limits->on($today)->id;

        // The timeline is a handful of rows — one per change in the law — so it
        // is shown in full rather than paginated, and each row carries the date
        // its rule stopped applying so a reader can answer "what was the weekly
        // limit in August 2026" by looking.
        $versions = LegalHourLimit::query()
            ->withCount('workdays')
            ->chronological()
            ->get();

        $endDates = $this->endDates($versions);

        $rows = $versions
            ->map(fn (LegalHourLimit $version): array => [
                ...$this->figures($version),
                'id' => $version->id,
                'effective_until' => $endDates[$version->id],
                'status' => match (true) {
                    $version->id === $inForceId => 'in_force',
                    $version->effective_from->greaterThan($today) => 'scheduled',
                    default => 'superseded',
                },
                'calculated_days' => $version->workdays_count,
            ])
            ->sortBy(
                fn (array $row): mixed => $row[$sort],
                SORT_REGULAR,
                $direction === 'desc',
            )
            ->values();

        return Inertia::render('saas/legal-hour-limits/index', [
            'versions' => ['data' => $rows],
            'filters' => ['sort' => $sort, 'direction' => $direction],
            'today' => $today->toDateString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('saas/legal-hour-limits/create');
    }

    public function store(Request $request, LegalHourLimitVersions $versions): RedirectResponse
    {
        $data = $request->validate([
            ...$this->figureRules(),
            // Not decoration: the acknowledgement is what makes the global
            // reach of the change something the user was told before saving
            // rather than something they are assumed to have known.
            'acknowledged_global_effect' => ['accepted'],
        ], $this->figureMessages());

        $version = $versions->add($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.saas_legal_hour_limits.flash.created', [
                'date' => $version->effective_from->format('d-m-Y'),
            ]),
        ]);

        return to_route('saas.legal-hour-limits.index');
    }

    /**
     * The correction screen, which is where a used version is edited from —
     * there is no plain edit form, because changing a recorded figure changes
     * what every day judged against it should have reported.
     */
    public function correct(LegalHourLimit $legalHourLimit): Response
    {
        return Inertia::render('saas/legal-hour-limits/correct', [
            'version' => [
                ...$this->figures($legalHourLimit),
                'id' => $legalHourLimit->id,
                'calculated_days' => $legalHourLimit->workdays()->count(),
            ],
        ]);
    }

    public function update(
        Request $request,
        LegalHourLimit $legalHourLimit,
        CorrectLegalHourLimit $correct,
    ): RedirectResponse {
        $data = $request->validate([
            ...$this->figureRules($legalHourLimit),
            'reason' => ['required', 'string', 'max:1000'],
        ], $this->figureMessages());

        $corrections = $this->changedFigures($legalHourLimit, $data);

        if ($corrections === []) {
            return back()->withErrors([
                'reason' => __('ui.saas_legal_hour_limits.validation.unchanged'),
            ]);
        }

        $recalculated = $correct->handle($legalHourLimit, $corrections, $data['reason']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.saas_legal_hour_limits.flash.corrected', ['count' => $recalculated]),
        ]);

        return to_route('saas.legal-hour-limits.index');
    }

    /**
     * Validation for the legal figures themselves, shared by appending a
     * version and correcting one.
     *
     * @return array<string, array<int, mixed>>
     */
    private function figureRules(?LegalHourLimit $version = null): array
    {
        return [
            'effective_from' => [
                'required', 'date',
                Rule::unique('legal_hour_limits', 'effective_from')->ignore($version),
            ],
            'ordinary_weekly_hours' => ['required', 'numeric', 'min:1', 'max:168'],
            'ordinary_daily_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'max_overtime_daily_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'max_overtime_weekly_hours' => ['required', 'numeric', 'min:0', 'max:168'],
            'max_total_daily_hours' => ['required', 'numeric', 'min:1', 'max:24', 'gte:ordinary_daily_hours'],
            'max_total_weekly_hours' => ['required', 'numeric', 'min:1', 'max:168', 'gte:ordinary_weekly_hours'],
            'legal_reference' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function figureMessages(): array
    {
        return [
            'effective_from.unique' => __('ui.saas_legal_hour_limits.validation.duplicate_date'),
            'acknowledged_global_effect.accepted' => __('ui.saas_legal_hour_limits.validation.acknowledge'),
            'max_total_daily_hours.gte' => __('ui.saas_legal_hour_limits.validation.total_below_ordinary_daily'),
            'max_total_weekly_hours.gte' => __('ui.saas_legal_hour_limits.validation.total_below_ordinary_weekly'),
        ];
    }

    /**
     * The submitted figures that actually differ from what is recorded, so the
     * correction — and the audit entry it writes — carries only what changed.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function changedFigures(LegalHourLimit $version, array $submitted): array
    {
        $changed = [];

        foreach (LegalHourLimit::FIGURES as $figure) {
            if (! array_key_exists($figure, $submitted)) {
                continue;
            }

            $value = $submitted[$figure];

            $current = $figure === 'effective_from'
                ? $version->effective_from->toDateString()
                : $version->{$figure};

            if ($figure === 'effective_from') {
                $value = Carbon::parse($value)->toDateString();
            } elseif (is_numeric($current)) {
                $value = (float) $value;
            }

            if ($value !== $current) {
                $changed[$figure] = $value;
            }
        }

        return $changed;
    }

    /**
     * The last date each version governs: the day before the next one starts,
     * and null for the version with nothing scheduled after it.
     *
     * @param  Collection<int, LegalHourLimit>  $versions  in chronological order
     * @return array<int, string|null>
     */
    private function endDates(Collection $versions): array
    {
        $endDates = [];

        foreach ($versions as $index => $version) {
            $next = $versions->get($index + 1);

            $endDates[$version->id] = $next?->effective_from->copy()->subDay()->toDateString();
        }

        return $endDates;
    }

    /**
     * @return array<string, mixed>
     */
    private function figures(LegalHourLimit $version): array
    {
        return [
            'effective_from' => $version->effective_from->toDateString(),
            'ordinary_weekly_hours' => $version->ordinary_weekly_hours,
            'ordinary_daily_hours' => $version->ordinary_daily_hours,
            'max_overtime_daily_hours' => $version->max_overtime_daily_hours,
            'max_overtime_weekly_hours' => $version->max_overtime_weekly_hours,
            'max_total_daily_hours' => $version->max_total_daily_hours,
            'max_total_weekly_hours' => $version->max_total_weekly_hours,
            'legal_reference' => $version->legal_reference,
            'notes' => $version->notes,
        ];
    }
}
