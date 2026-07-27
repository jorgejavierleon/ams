<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Issues Sanctum personal access tokens to the employee mobile app. A device
 * exchanges the employee's credentials for a bearer token once, then sends it on
 * every subsequent API request. Re-authenticating from the same device replaces
 * that device's previous token.
 */
class TokenController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function issueToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // A deactivated employee must not be able to obtain a mobile token.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.inactive')],
            ]);
        }

        // One token per named device: revoke the device's previous token so a
        // re-login never leaves stale credentials valid.
        $user->tokens()->where('name', $validated['device_name'])->delete();

        return response()->json([
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
        ]);
    }
}
