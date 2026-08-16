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
     * holding one of the section's permissions; the queue (KOL-44) and the
     * request flow (KOL-45) each add their own screens here as they land.
     * Pactos (KOL-42) are reachable only by whoever holds
     * `Manage:OvertimeAuthorization`, the same permission that gates the
     * pactos routes themselves.
     */
    public function index(Request $request, OrganizationSettings $settings): Response
    {
        return Inertia::render('overtime/index', [
            'can' => [
                'managePacts' => $request->user()->can('Manage:OvertimeAuthorization'),
                'viewQueue' => $request->user()->can('ViewTeam:OvertimeAuthorization')
                    || $request->user()->can('Manage:OvertimeAuthorization'),
                // Mode A only (KOL-45): hidden entirely under pure post-hoc,
                // where the request flow does not apply.
                'request' => $settings->overtimeAuthorizationMode()->allowsRequests()
                    && $request->user()->can('RequestOwn:OvertimeAuthorization'),
            ],
        ]);
    }
}
