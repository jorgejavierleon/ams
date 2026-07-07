<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Models\Company;
use App\Models\Premise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PremiseController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'code', 'address', 'created_at'],
            'name',
        );

        $premises = Premise::query()
            ->with('company:id,social_reason')
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('premises/index', [
            'premises' => $premises->through(fn (Premise $premise) => [
                'id' => $premise->id,
                'name' => $premise->name,
                'company' => $premise->company?->social_reason,
                'address' => $premise->address,
                'has_coordinates' => $premise->lat !== null && $premise->lng !== null,
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('premises/create', [
            'companies' => $this->companyOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePremise($request);

        Premise::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.premises.flash.created')]);

        return to_route('premises.index');
    }

    public function edit(Premise $premise): Response
    {
        return Inertia::render('premises/edit', [
            'premise' => [
                'id' => $premise->id,
                'company_id' => $premise->company_id,
                'name' => $premise->name,
                'code' => $premise->code,
                'country' => $premise->country,
                'region' => $premise->region,
                'commune' => $premise->commune,
                'address' => $premise->address,
                'lat' => $premise->lat,
                'lng' => $premise->lng,
                'responsable_name' => $premise->responsable_name,
                'responsable_email' => $premise->responsable_email,
                'responsable_phone' => $premise->responsable_phone,
            ],
            'companies' => $this->companyOptions(),
        ]);
    }

    public function update(Request $request, Premise $premise): RedirectResponse
    {
        $data = $this->validatePremise($request);

        $premise->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.premises.flash.updated')]);

        return to_route('premises.index');
    }

    public function destroy(Premise $premise): RedirectResponse
    {
        if ($premise->activeUsers()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.premises.flash.has_employees')]);

            return back();
        }

        $premise->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.premises.flash.deleted')]);

        return to_route('premises.index');
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function companyOptions(): array
    {
        return Company::query()
            ->orderBy('social_reason')
            ->get(['id', 'social_reason'])
            ->map(fn (Company $company) => ['value' => $company->id, 'label' => $company->social_reason])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePremise(Request $request): array
    {
        return $request->validate([
            'company_id' => [
                'required', 'integer',
                Rule::exists('companies', 'id')
                    ->where('organization_id', Company::currentOrganizationId()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'responsable_name' => ['nullable', 'string', 'max:255'],
            'responsable_email' => ['nullable', 'email', 'max:255'],
            'responsable_phone' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
