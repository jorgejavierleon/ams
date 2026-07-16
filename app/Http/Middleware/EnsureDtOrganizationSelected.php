<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards organization-scoped DT views behind an active audit session.
 *
 * A DT inspector must pick which employer they are auditing before any tenant
 * data is shown. Until the choice is stored in the session, every gated request
 * is bounced to the organization selector.
 */
class EnsureDtOrganizationSelected
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('dt_organization_id') === null) {
            return redirect()->route('dt.organization.select');
        }

        return $next($request);
    }
}
