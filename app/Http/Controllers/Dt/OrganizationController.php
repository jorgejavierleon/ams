<?php

namespace App\Http\Controllers\Dt;

use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Mail\DtAuditNotification;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization selector for the Labor Department (DT) audit session.
 *
 * Implements the "identificación del empleador fiscalizado" screen of
 * Resolución 38 (Art. 24): a search by employer name or RUT alongside the full
 * alphabetical list of employers. DT inspectors are not tenant-bound, so before
 * viewing any employer's data they must choose which one they are auditing. The
 * choice is stored in the session and drives {@see OrganizationScope} for the
 * rest of the session, until logout or a new selection.
 */
class OrganizationController extends Controller
{
    use ResolvesTableSort;

    /**
     * List the employers an inspector can audit — filtered by an optional name
     * or RUT search — along with the one currently selected for the session.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'rut'],
            'name',
        );

        $organizations = Organization::query()
            ->when($search, function ($query) use ($search) {
                $rut = Rut::normalize($search);

                $query->where(fn ($group) => $group
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$rut}%"));
            })
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dt/select-organization', [
            'organizations' => $organizations->through(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'rut' => $organization->formatted_rut,
            ]),
            'selectedId' => $request->session()->get('dt_organization_id'),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Store the chosen employer as the audit session organization, notify the
     * employer that a labor inspection has begun (Art. 24 b), and hand the
     * inspector off to the dashboard.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $organization = Organization::query()->findOrFail($request->integer('organization_id'));

        $request->session()->put('dt_organization_id', $organization->id);
        $request->session()->put('organization_name', $organization->name);

        if ($organization->email !== null) {
            Mail::to($organization->email)->send(new DtAuditNotification);
        }

        return to_route('dt.dashboard');
    }
}
