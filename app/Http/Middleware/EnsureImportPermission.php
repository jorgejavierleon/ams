<?php

namespace App\Http\Middleware;

use App\Services\Imports\ImportResourceRegistry;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces the bulk-import wizard's old static `permission:Import:Employee`
 * route middleware (KOL-107): the permission that gates
 * `imports/{resourceType}/...` now depends on which resource type is in the
 * URL, resolved through {@see ImportResourceRegistry} — an unregistered
 * resource type 404s here before any permission check runs (AC #3).
 */
class EnsureImportPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $definition = ImportResourceRegistry::findOrFail((string) $request->route('resourceType'));

        if (! $request->user()?->canAny([$definition->permission])) {
            throw UnauthorizedException::forPermissions([$definition->permission]);
        }

        return $next($request);
    }
}
