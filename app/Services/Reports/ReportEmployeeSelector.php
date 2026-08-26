<?php

namespace App\Services\Reports;

use App\Enums\ContractType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\CostCenter;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\EmployeeSelection;
use App\Support\ReportEmployeeFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Resolves the employee pool every payroll report filters and selects from
 * (RF-7, KOL-19). Two responsibilities share one query builder so the picker
 * table and the final resolved selection can never disagree about who
 * matches: {@see self::paginate()} lists candidates for the picker,
 * {@see self::resolve()} turns a filter + selection pair into the flat
 * `list<int>` the aggregation service (KOL-13) and the integrity check
 * (KOL-14) both already consume as their `$userIds` parameter.
 *
 * {@see User} carries no organization global scope, so every query here
 * filters `organization_id` explicitly against {@see CurrentOrganization}
 * (KOL-19 AC #6).
 */
class ReportEmployeeSelector
{
    /**
     * @return Builder<User>
     */
    public function candidates(ReportEmployeeFilters $filters): Builder
    {
        return User::query()
            ->employees()
            ->where('organization_id', CurrentOrganization::id())
            ->when($filters->premiseIds, fn (Builder $query, array $ids) => $query->whereIn('premise_id', $ids))
            ->when($filters->costCenterIds, fn (Builder $query, array $ids) => $query->whereIn('cost_center_id', $ids))
            ->when($filters->positionIds, fn (Builder $query, array $ids) => $query->whereIn('position_id', $ids))
            ->when($filters->contractTypes, fn (Builder $query, array $types) => $query->whereIn('contract_type', $types));
    }

    /**
     * Paginated candidates for the employee-picker table, ordered by name and
     * optionally narrowed by a search term (name, email or RUT).
     *
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(
        ReportEmployeeFilters $filters,
        ?string $search = null,
        string $sort = 'name',
        string $direction = 'asc',
        int $perPage = 10,
    ): LengthAwarePaginator {
        return $this->candidates($filters)
            ->when($search, fn (Builder $query, string $term) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('rut', 'like', "%{$term}%")))
            ->with(['position:id,name', 'premise:id,name', 'costCenter:id,name'])
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Resolve a filter + selection pair to the flat list of employee ids it
     * names — the single object every downstream consumer shares (AC #4).
     *
     * @return list<int>
     */
    public function resolve(ReportEmployeeFilters $filters, EmployeeSelection $selection): array
    {
        if (! $selection->selectAll && $selection->ids === []) {
            // Explicit "nothing selected" (AC #7) — never silently the whole company.
            return [];
        }

        if ($selection->selectAll) {
            $candidateIds = $this->candidates($filters)->pluck('id')->all();

            return array_values(array_diff($candidateIds, $selection->ids));
        }

        // A manual pick names specific employees independent of the filter
        // dimensions — the filters narrow the *picker*, not which explicitly
        // chosen ids count — but it must still never reach across tenants
        // (AC #6) or name a non-employee record.
        return array_values(array_map(
            intval(...),
            User::query()
                ->employees()
                ->where('organization_id', CurrentOrganization::id())
                ->whereIn('id', $selection->ids)
                ->pluck('id')
                ->all(),
        ));
    }

    /**
     * Facet option lists for the filter dropdowns, scoped to the current
     * organization by each model's own
     * {@see BelongsToOrganization}. Each option carries a `count` of how many
     * of the organization's employees currently have that value — a simple,
     * filter-independent tally (not narrowed by any other active facet) that
     * gives the picker a sense of scale without a combinatorial per-facet
     * aggregation.
     *
     * @return array{
     *     premises: array<int, array{value: string, label: string, count: int}>,
     *     positions: array<int, array{value: string, label: string, count: int}>,
     *     costCenters: array<int, array{value: string, label: string, count: int}>,
     *     contractTypes: array<int, array{value: string, label: string, count: int}>,
     * }
     */
    public function optionsFor(): array
    {
        $countsBy = fn (string $column): array => $this->candidates(new ReportEmployeeFilters)
            ->whereNotNull($column)
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->all();

        $premiseCounts = $countsBy('premise_id');
        $positionCounts = $countsBy('position_id');
        $costCenterCounts = $countsBy('cost_center_id');
        $contractTypeCounts = $countsBy('contract_type');

        return [
            'premises' => Premise::query()->orderBy('name')->get()
                ->map(fn (Premise $premise): array => [
                    'value' => (string) $premise->id,
                    'label' => $premise->name,
                    'count' => $premiseCounts[$premise->id] ?? 0,
                ])
                ->all(),
            'positions' => Position::query()->orderBy('name')->get()
                ->map(fn (Position $position): array => [
                    'value' => (string) $position->id,
                    'label' => $position->name,
                    'count' => $positionCounts[$position->id] ?? 0,
                ])
                ->all(),
            'costCenters' => CostCenter::query()->orderBy('name')->get()
                ->map(fn (CostCenter $costCenter): array => [
                    'value' => (string) $costCenter->id,
                    'label' => $costCenter->name,
                    'count' => $costCenterCounts[$costCenter->id] ?? 0,
                ])
                ->all(),
            'contractTypes' => array_map(
                fn (array $option): array => [...$option, 'count' => $contractTypeCounts[$option['value']] ?? 0],
                ContractType::options(),
            ),
        ];
    }
}
