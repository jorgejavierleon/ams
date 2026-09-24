---
id: KOL-126
title: 'Set up the MCP server: registration, Sanctum auth, and tool conventions'
status: Done
assignee: []
created_date: '2026-09-24 19:31'
updated_date: '2026-09-24 22:43'
labels:
  - mcp
  - backend
dependencies: []
priority: high
type: feature
ordinal: 126000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
This is the foundation the other MCP tickets build on — nothing exposes a tool until this exists. Registers a Laravel MCP web server (`laravel/mcp`) that lets a user's own AI agent authenticate as them and call tools that act with their exact ACL, no more and no less than what they can already do in the web app.

Deliverables:
- A `Laravel\Mcp\Server` subclass registered via `Mcp::web('/mcp/...', ...)`, protected by Sanctum token auth (reuses the existing `HasApiTokens` setup already on `User`).
- Every tool's `handle()` authorizes via `$request->user()->can(...)` against the *same* permissions/policies the web app already uses — an MCP tool call is authorization-equivalent to the human doing the same action in the UI, never broader.
- A shared base pattern (trait or abstract class) for tool response conventions (success/error shape, validation error formatting), reused by every tool added in the follow-up tickets.
- No domain tools are registered in this ticket — it ships an empty, authenticated server that other tickets attach tools to.

## User stories for manual testing (Gherkin)

Scenario: An authenticated agent can reach the MCP server
  Given a user has a valid Sanctum API token
  When their agent sends an authenticated MCP request (e.g. via the MCP Inspector) to the server endpoint
  Then the server responds successfully with no domain tools listed yet

Scenario: An unauthenticated request is rejected
  Given no valid Sanctum token is presented
  When a request is made to the MCP server endpoint
  Then the server rejects it without exposing any tool or data
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A Laravel MCP web server is registered and reachable over HTTP
- [x] #2 The server requires a valid Sanctum token; requests without one are rejected
- [x] #3 A documented, reusable pattern exists for a tool to authorize via the current user's permissions before doing anything
- [x] #4 Pest tests cover authenticated success and unauthenticated rejection
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. vendor:publish routes/ai.php; register empty Server subclass App\Mcp\Servers\AmsServer via Mcp::web('/mcp', ...) with auth:sanctum middleware.
2. Create abstract App\Mcp\Servers\Concerns or base Tool class (e.g. App\Mcp\Tools\AuthorizedTool) providing a authorize(string $ability, mixed $arg=null) helper that returns Response::error on failure, and a validated() wrapper standardizing validation-error responses -- documented via PHPDoc as the pattern follow-up tickets must use.
3. Write Pest feature tests: authenticated Sanctum request reaches server successfully (empty tool list), unauthenticated request rejected (401/403), and a unit test exercising the base tool class's authorize helper (deny + allow) using a throwaway test tool.
4. Run pint --dirty, sail artisan test --compact filtered to Mcp tests.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Registered Mcp::web('/mcp/kolvi', KolviServer::class) with auth:sanctum middleware in routes/ai.php (auto-loaded by laravel/mcp's service provider, no bootstrap/app.php routing change needed).

Base authorization pattern: App\Mcp\Tools\AuthorizedTool (abstract class extending Laravel\Mcp\Server\Tool) with an authorize($request, $ability, $arguments=null) helper. Chose abstract class over trait specifically because Larastan/PHPStan can't safely analyse a trait with zero use-sites ('trait is used zero times and is not analysed') until a follow-up ticket adds a real tool -- an abstract class has no such limitation.

Found and fixed a pre-existing gap: bootstrap/app.php's shouldRenderJsonWhen() only matched 'api/*', so an unauthenticated request to /mcp/* was getting redirected to /login (302) instead of a 401 JSON response. Added '|| $request->is('mcp/*')' to the same closure.

Validation-error formatting is already handled by the framework itself (ValidationException and AuthorizationException thrown inside a tool's handle() are auto-converted to Response::error(...) by Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses) -- no custom wrapper needed for that part of AC #3.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Registered an authenticated, empty Laravel MCP web server (KolviServer) at /mcp/kolvi behind auth:sanctum, plus an AuthorizedTool abstract base class that documents the authorize()-before-acting pattern for follow-up tool tickets. Fixed a pre-existing gap in bootstrap/app.php where unauthenticated requests outside api/* silently redirected instead of returning 401. Verified with sail artisan test --filter=Mcp (4/4 passing: authenticated reachability with empty tool list, unauthenticated 401 rejection, authorize() allow/deny), Larastan (0 errors), and Pint (clean).
<!-- SECTION:FINAL_SUMMARY:END -->
