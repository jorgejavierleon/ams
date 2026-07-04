<?php

namespace App\Http\Controllers\Saas;

use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;

        $organizations = Organization::query()
            ->withCount('users')
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('saas/organizations/index', [
            'organizations' => $organizations->through(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'plan' => $organization->plan->label(),
                'users_count' => $organization->users_count,
                'created_at' => $organization->created_at?->toDateString(),
            ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('saas/organizations/create', [
            'plans' => Plan::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Organization::create($this->validateOrganization($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.organizations.flash.created')]);

        return to_route('saas.organizations.index');
    }

    public function edit(Organization $organization): Response
    {
        return Inertia::render('saas/organizations/edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'plan' => $organization->plan->value,
            ],
            'plans' => Plan::options(),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $organization->update($this->validateOrganization($request, $organization));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.organizations.flash.updated')]);

        return to_route('saas.organizations.index');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        if ($organization->users()->exists()) {
            $organization->delete();
            $message = __('ui.organizations.flash.archived');
        } else {
            $organization->forceDelete();
            $message = __('ui.organizations.flash.deleted');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('saas.organizations.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrganization(Request $request, ?Organization $organization = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('organizations', 'slug')->ignore($organization),
            ],
            'plan' => ['required', Rule::enum(Plan::class)],
        ]);
    }
}
