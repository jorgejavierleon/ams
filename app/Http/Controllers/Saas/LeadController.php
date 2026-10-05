<?php

namespace App\Http\Controllers\Saas;

use App\Concerns\ResolvesTablePerPage;
use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    use ResolvesTablePerPage;
    use ResolvesTableSort;

    /**
     * Leads captured from the public landing page's contact form (KOL-139).
     * Organization-less by design, so this query is intentionally unscoped
     * by tenant.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'company', 'email', 'created_at'],
            'created_at',
            'desc',
        );
        $perPage = $this->resolveTablePerPage($request);

        $leads = Lead::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('saas/leads/index', [
            'leads' => $leads->through(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'company' => $lead->company,
                'email' => $lead->email,
                'message' => $lead->message,
                'created_at' => $lead->created_at?->format('Y-m-d H:i:s') ?? '',
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.leads.flash.deleted')]);

        return back();
    }
}
