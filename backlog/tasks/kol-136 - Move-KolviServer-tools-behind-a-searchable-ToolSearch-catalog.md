---
id: KOL-136
title: Move KolviServer tools behind a searchable ToolSearch catalog
status: Done
assignee: []
created_date: '2026-09-29 09:33'
updated_date: '2026-09-29 09:59'
labels: []
dependencies: []
ordinal: 146000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The Kolvi MCP server advertised all 18 tools directly, bloating the initial tool listing sent to any connecting agent. Laravel MCP's searchable-tool-catalog pattern (ToolSearch) exposes just two tools, search_tools and execute_tools, and lets the agent look up and invoke the real tools on demand.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 KolviServer wraps all registered tools in Laravel\\Mcp\\Server\\Tools\\ToolSearch so tools/list returns only search_tools and execute_tools
- [x] #2 Every previously-registered tool is still discoverable and callable through search_tools/execute_tools
- [x] #3 laravel/mcp is upgraded to a version that ships ToolSearch (via laravel/boost)
- [x] #4 Existing MCP test coverage is rewritten to call tools through the catalog and still passes
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
laravel/mcp v0.8.1 (pulled in transitively via laravel/boost) did not have ToolSearch; required bumping laravel/boost 2.4.10->2.10.0 and laravel/mcp 0.8.1->1.0.1 via composer update (no composer.json constraint changes needed, already allowed by ^2.2). This cascaded into laravel/framework 13.16.1->13.33.0 (still within ^13.7). Catalog-wrapped tools are no longer reachable via a direct tools/call; they're only invokable through execute_tools, which wraps the response as a JSON string and, since it can emit notifications, answers over an SSE stream instead of a plain JSON body. Added tests/Feature/Mcp/Concerns/CatalogToolResponse.php + a mcpTool() Pest helper (tests/Pest.php) to call tools through execute_tools and decode both the SSE and plain-JSON response shapes, replacing the old KolviServer::actingAs()->tool() direct-call pattern across all 5 MCP test files. Also fixed one pre-existing float-vs-int JSON round-trip assertion in LeaveToolsTest.php (2.0 -> 2, 10.0 -> 10) surfaced by the laravel/mcp upgrade: whole-number floats now correctly collapse to ints on the real wire, matching actual client behavior.

Full sequential suite (sail artisan test --compact, matching project convention) verified clean after the dependency bump: 1633/1637 passed, 4 skipped, 0 failed (882.96s). An earlier ad hoc run with --parallel showed 55 failures, all 'Call to undefined function validRut()' in ImportWizardTest.php; confirmed as a pre-existing test-isolation issue unrelated to this change (validRut() is declared globally in CompanyManagementTest.php, and Paratest splits files across workers that don't all load it) -- reproduced and fixed nothing, just avoided --parallel for the real check.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
KolviServer now registers all 18 tools inside a ToolSearch catalog. Verified: MCP test group passes 72/72 (tests/Feature/Mcp), Pint clean, PHPStan (larastan) clean on app/Mcp. Full project test suite run in background for final regression check on the dependency bump.
<!-- SECTION:FINAL_SUMMARY:END -->
