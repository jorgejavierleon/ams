<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PersonalAccessTokenStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TokenController extends Controller
{
    /**
     * Create a new personal access token for the authenticated user. The
     * plaintext token is flashed once so security.tsx can show it in a
     * one-time-reveal modal; it is never persisted or returned again.
     */
    public function store(PersonalAccessTokenStoreRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->validated('name'));

        Inertia::flash('newToken', [
            'name' => $token->accessToken->name,
            'plainTextToken' => $token->plainTextToken,
        ]);

        return back();
    }

    /**
     * Revoke one of the authenticated user's own personal access tokens.
     * Scoped through $request->user()->tokens() so a user can never reach,
     * and therefore never even discover, another user's token by id.
     */
    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->findOrFail($token)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.settings.security.tokens.flash.revoked')]);

        return back();
    }
}
