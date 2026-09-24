<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for every Kolvi MCP tool.
 *
 * Conventions:
 * - Call authorize() first in handle() and return its response when it is
 *   non-null. A tool call must never be authorized more broadly than the
 *   same action performed by the user in the web app.
 * - Return Response::structured([...]) for successful results.
 * - Return Response::error('message') for expected failures.
 * - Validate arguments with $request->validate(...); Laravel MCP formats a
 *   thrown ValidationException into a tool error response automatically.
 */
abstract class AuthorizedTool extends Tool
{
    /**
     * Authorize the current request against the given ability, using the
     * same policies/permissions the web app already enforces.
     *
     * Returns an error Response when unauthorized, or null when the tool
     * may proceed:
     *
     *     if ($response = $this->authorize($request, 'viewTeam', Leave::class)) {
     *         return $response;
     *     }
     */
    protected function authorize(Request $request, string $ability, mixed $arguments = null): ?Response
    {
        if ($request->user()->can($ability, $arguments)) {
            return null;
        }

        return Response::error("You are not authorized to perform the [{$ability}] action.");
    }
}
