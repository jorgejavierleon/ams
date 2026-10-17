<?php

namespace App\Http\Middleware;

use App\Models\TotemDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admits only a TotemDevice whose token carries the kiosk:punch ability, so an
 * employee token can never drive the kiosk surface.
 */
class EnsureTotemDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = auth('sanctum')->user();

        abort_unless(
            $device instanceof TotemDevice && $device->tokenCan(TotemDevice::PUNCH_ABILITY),
            403,
        );

        return $next($request);
    }
}
