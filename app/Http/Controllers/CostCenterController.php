<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Models\CostCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for the tenant's *centro de costo* catalogue, in the same shape as
 * {@see PositionController}: a searchable list with an inline create/edit
 * dialog.
 */
class CostCenterController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'code', 'active_users_count'],
            'name',
        );

        $costCenters = CostCenter::query()
            ->withCount('activeUsers')
            ->when($search, fn ($query) => $query->where(fn ($group) => $group
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('cost-centers/index', [
            'costCenters' => $costCenters->through(fn (CostCenter $costCenter) => [
                'id' => $costCenter->id,
                'name' => $costCenter->name,
                'code' => $costCenter->code,
                'active_users_count' => $costCenter->active_users_count,
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CostCenter::create($this->validateCostCenter($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.cost_centers.flash.created')]);

        return to_route('cost-centers.index');
    }

    public function update(Request $request, CostCenter $costCenter): RedirectResponse
    {
        $costCenter->update($this->validateCostCenter($request, $costCenter));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.cost_centers.flash.updated')]);

        return to_route('cost-centers.index');
    }

    /**
     * Refuse to delete a cost centre that still has active employees charging
     * to it, so payroll never loses the dimension mid-period.
     */
    public function destroy(CostCenter $costCenter): RedirectResponse
    {
        if ($costCenter->activeUsers()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.cost_centers.flash.has_employees')]);

            return to_route('cost-centers.index');
        }

        $costCenter->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.cost_centers.flash.deleted')]);

        return to_route('cost-centers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCostCenter(Request $request, ?CostCenter $costCenter = null): array
    {
        $request->merge([
            'code' => $request->string('code')->trim()->value() ?: null,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('cost_centers', 'code')
                    ->where('organization_id', CostCenter::currentOrganizationId())
                    ->ignore($costCenter),
            ],
        ]);
    }
}
