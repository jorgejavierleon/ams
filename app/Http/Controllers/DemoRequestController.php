<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    /**
     * Record a demo request submitted from the public landing page
     * (KOL-139). Redirects back to the same anchor so the page's own
     * client-side state (not a flash message) decides when to swap the form
     * for the confirmation.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        DemoRequest::create($data);

        return back();
    }
}
