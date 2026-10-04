<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Record a lead submitted from the public landing page's contact form
     * (KOL-139). Redirects back to the same anchor so the page's own
     * client-side state (not a flash message) decides when to swap the form
     * for the confirmation.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        Lead::create($data);

        return back();
    }
}
