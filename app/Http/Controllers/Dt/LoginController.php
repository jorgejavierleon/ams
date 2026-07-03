<?php

namespace App\Http\Controllers\Dt;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/dt-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = Auth::guard('dt');

        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        if (! $guard->user()->is_dt) {
            $guard->logout();

            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dt.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('dt')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dt.login');
    }
}
