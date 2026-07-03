<?php

namespace App\Http\Controllers\Dt;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/dt-password-change');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var User $user */
        $user = Auth::guard('dt')->user();

        $user->update([
            'password' => $request->password,
            'password_changed_at' => now(),
        ]);

        return redirect()->route('dt.dashboard');
    }
}
