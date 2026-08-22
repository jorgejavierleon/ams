<?php

namespace App\Http\Controllers;

use App\Services\OrganizationSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    /**
     * The overtime section's landing page (KOL-43). Reachable by anyone
     * holding one of the section's permissions. Post-hoc approval lives on
     * Jornadas (KOL-71) and Mode A requests have their own screen (KOL-72);
     * the old combined queue is gone (KOL-74). Pactos (KOL-42) are reachable
     * only by whoever holds `Manage:OvertimeAuthorization`, the same
     * permission that gates the pactos routes themselves.
     */
    public function index(Request $request, OrganizationSettings $settings): Response
    {
        return Inertia::render('overtime/index', [
            'can' => [
                'managePacts' => $request->user()->can('Manage:OvertimeAuthorization'),
                // Mode A only (KOL-45): hidden entirely under pure post-hoc,
                // where the request flow does not apply.
                'request' => $settings->overtimeAuthorizationMode()->allowsRequests()
                    && $request->user()->can('RequestOwn:OvertimeAuthorization'),
                // KOL-72: the standalone Solicitudes screen, same audience as
                // the old queue's requests tab — hidden under pure post-hoc,
                // where there is nothing to review.
                'viewRequests' => $settings->overtimeAuthorizationMode()->allowsRequests()
                    && ($request->user()->can('ViewTeam:OvertimeAuthorization')
                        || $request->user()->can('Manage:OvertimeAuthorization')),
                // KOL-47: HR manages every employee's rest-day balance from
                // the same permission as pactos; an employee sees only their
                // own.
                'manageRestDayBalances' => $request->user()->can('Manage:OvertimeAuthorization'),
                'viewOwnRestDayBalance' => $request->user()->can('ViewOwn:OvertimeAuthorization'),
            ],
        ]);
    }
}
