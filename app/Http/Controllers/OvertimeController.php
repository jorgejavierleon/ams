<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    /**
     * The overtime section's landing page (KOL-43). Reachable by anyone
     * holding one of the section's permissions; the queue (KOL-44), the
     * request flow (KOL-45) and pactos (KOL-42) each add their own screens
     * here as they land.
     */
    public function index(): Response
    {
        return Inertia::render('overtime/index');
    }
}
