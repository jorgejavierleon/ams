<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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
            // Honeypot (KOL-144): a field real users never see or fill.
            // Bots that fill every input trip it; the submission is then
            // silently dropped, responding exactly like a success so the
            // bot has no signal to adapt to.
            'website' => ['nullable', 'string'],
        ]);

        if (filled($data['website'] ?? null)) {
            return back();
        }

        Lead::create(Arr::except($data, 'website'));

        return back();
    }
}
