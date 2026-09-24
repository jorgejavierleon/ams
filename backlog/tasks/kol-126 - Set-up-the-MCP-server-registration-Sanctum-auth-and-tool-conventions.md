---
id: KOL-126
title: 'Set up the MCP server: registration, Sanctum auth, and tool conventions'
status: To Do
assignee: []
created_date: '2026-09-24 19:31'
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
- [ ] #1 A Laravel MCP web server is registered and reachable over HTTP
- [ ] #2 The server requires a valid Sanctum token; requests without one are rejected
- [ ] #3 A documented, reusable pattern exists for a tool to authorize via the current user's permissions before doing anything
- [ ] #4 Pest tests cover authenticated success and unauthenticated rejection
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
