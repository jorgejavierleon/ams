<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the employee API for employee tokens. A TotemDevice authenticates
 * through the same `sanctum` guard, so without this its narrow kiosk token
 * would reach routes that assume `$request->user()` is a User.
 */
class EnsureUserToken
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof User, 403);

        return $next($request);
    }
}
