---
id: KOL-128
title: Add MCP tools for overtime requests (self-service + supervisor review)
status: Done
assignee:
  - '@jorge'
created_date: '2026-09-24 19:32'
updated_date: '2026-09-26 15:18'
labels:
  - mcp
  - overtime
  - backend
dependencies:
  - KOL-126
priority: high
type: feature
ordinal: 128000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Same shape as the leave tools (KOL-127), wrapping `My\OvertimeRequestController` and `OvertimeRequestController`/`OvertimeRequestPolicy` 1:1. Uses only permissions that already exist (`RequestOwn:OvertimeAuthorization`, `ViewOwn:OvertimeAuthorization`, `ViewTeam:OvertimeAuthorization`, `ApproveTeam:OvertimeAuthorization`) — unlike leave, there's no admin-initiated "create on behalf of an employee" flow in the web app today, so this ticket doesn't add one via MCP either. If a capability doesn't exist for a human in the UI, it doesn't exist for their agent.

Self-service tools:
- Create an overtime request for myself
- View my own overtime requests and their status

Review tools (supervisor-scoped exactly as `OvertimeRequestPolicy` enforces today):
- View my team's overtime requests
- Approve or reject an overtime request (rejection requires `decision_reason`, matching the model's existing `booted()` enforcement)

Note: `OvertimeRequest` is distinct from `OvertimeAuthorization` (the separate, already-worked/computed record) — these tools operate on requests, not on authorizations.

## User stories for manual testing (Gherkin)

Scenario: An employee's agent requests overtime on their behalf
  Given an employee has a valid MCP session
  When their agent calls the create-overtime-request tool with valid details
  Then a pending overtime request is created for that employee

Scenario: A supervisor's agent approves a team member's overtime request
  Given a supervisor has a valid MCP session and one of their direct reports has a pending overtime request
  When their agent calls the approve-overtime-request tool
  Then the request becomes approved, with reviewed_by/reviewed_at recorded

Scenario: A supervisor's agent rejects an overtime request without a reason
  Given a supervisor has a valid MCP session and a pending overtime request from their team
  When their agent calls the reject-overtime-request tool without a decision reason
  Then the tool refuses, matching the model's existing validation

Scenario: A supervisor's agent cannot review overtime outside their team
  Given a supervisor has a valid MCP session
  When their agent calls the approve-overtime-request tool for an employee who is not their direct report
  Then the tool refuses, same as OvertimeRequestPolicy enforces in the web app today
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Tools exist for: create (self), view own, view team, approve, reject
- [x] #2 Every tool's authorization matches the equivalent web action exactly: same permission, same policy, same supervisor scoping
- [x] #3 No admin-initiated "create on behalf of" tool is added, since no such capability exists in the web app today
- [x] #4 Pest tests cover each tool's success and denial paths
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
1. app/Mcp/Tools/Overtime/Concerns/FormatsOvertimeRequest.php - shared structured-content shape (id, employee_id, employee, date, requested_hours, reason, status/status_label, reviewed_by, decision_reason), mirroring FormatsLeave.
2. app/Mcp/Tools/Overtime/CreateOvertimeRequestTool.php - self-service create, gate RequestOwn:OvertimeAuthorization, mirrors My\OvertimeRequestController::store 1:1: mode-allows-requests gate, workday_id override (KOL-79), retroactive-window check, requester is always the caller.
3. app/Mcp/Tools/Overtime/ViewOwnOvertimeRequestsTool.php - gate ViewOwn:OvertimeAuthorization, mirrors My\OvertimeRequestController::index (status filter only, no pagination/sort - matches ViewOwnLeavesTool precedent).
4. app/Mcp/Tools/Overtime/ViewTeamOvertimeRequestsTool.php - gate policy ability 'viewTeam' on OvertimeRequest::class, mirrors OvertimeRequestController::index scoping (admin/Owner org-wide vs supervisor_id) and its default-to-pending status filter.
5. app/Mcp/Tools/Overtime/ApproveOvertimeRequestTool.php - gate policy ability 'approve' on the loaded OvertimeRequest, abort if not pending, calls $overtimeRequest->approve() + OvertimeRequestApproved notification, mirrors OvertimeRequestController::approve.
6. app/Mcp/Tools/Overtime/RejectOvertimeRequestTool.php - gate policy ability 'reject', required reason, calls $overtimeRequest->reject() + OvertimeRequestRejected notification, mirrors OvertimeRequestController::reject.
7. Register all 5 tools in KolviServer::$tools.
8. Pest tests: tests/Feature/Mcp/Overtime/OvertimeToolsTest.php (group 'mcp'), success+denial per tool including mode-gating, workday_id override, retroactive window, supervisor scoping, no-admin-create-on-behalf. Update KolviServerTest's tool-list assertion.
9. vendor/bin/pint --dirty --format agent; sail artisan test --compact filtered to Overtime/Mcp tests; confirm no regressions in existing overtime test suites.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented 5 tools under app/Mcp/Tools/Overtime/, registered in KolviServer:
- create-overtime-request (self-service, gated RequestOwn:OvertimeAuthorization) mirrors My\OvertimeRequestController::store 1:1 - honors overtimeAuthorizationMode()->allowsRequests() gate, workday_id override (KOL-79, forces hours to the workday's calculated figure), and the tenant's retroactive-window check.
- view-own-overtime-requests (ViewOwn:OvertimeAuthorization) - status filter only, matching the ViewOwnLeavesTool precedent (no pagination/sort, those are UI-only concerns).
- view-team-overtime-requests (policy ability 'viewTeam' on OvertimeRequest::class) - admin/Owner org-wide vs supervisor_id scoping, and defaults to pending-only unless status=all is passed, mirroring OvertimeRequestController::index's statusFilter().
- approve-overtime-request / reject-overtime-request (policy abilities 'approve'/'reject') - call the model's own approve()/reject() (OvertimeRequest, unlike Leave, has no separate Manager service), abort if not pending, send the existing OvertimeRequestApproved/Rejected notifications.

No admin-initiated "create on behalf of an employee" tool was added (AC #3) - OvertimeRequestPolicy has no `create`-for-others ability and the web app has no such form today, unlike Leave's Create:Leave permission.

Noted but did not change: OvertimeRequestPolicy::canDecide() has no admin-role bypass (only Owner bypasses via Gate::before) - a plain admin cannot approve/reject a report outside their own direct reports, same limitation that already exists in the web app (OvertimeRequestReviewTest has no "admin approves any report" test either). The MCP tool mirrors this as-is per the ticket's "supervisor-scoped exactly as OvertimeRequestPolicy enforces today" requirement.

Tests: tests/Feature/Mcp/Overtime/OvertimeToolsTest.php (24 tests, success+denial per tool, using KolviServer::actingAs()->tool()). Updated KolviServerTest's tool-list assertion to include the 5 new tool names.

Verified: pint clean; Larastan clean (0 errors on app/Mcp/Tools/Overtime + KolviServer.php); sail artisan test --filter="OvertimeToolsTest|KolviServerTest|AuthorizedToolTest" -> 24/24 passing; full regression on every Overtime-related suite (--filter=Overtime) -> 257/257 passing; full mcp group (--group=mcp) -> 49/49 passing. No TypeScript touched, so DoD #3 is not applicable.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added 5 MCP tools (app/Mcp/Tools/Overtime/) wrapping My\OvertimeRequestController and OvertimeRequestController/OvertimeRequestPolicy 1:1: create-overtime-request, view-own-overtime-requests, view-team-overtime-requests, approve-overtime-request, reject-overtime-request. No new domain logic and no admin-create-on-behalf tool, since none exists in the web app for overtime today. Verified with 24 new Pest tests (tests/Feature/Mcp/Overtime/OvertimeToolsTest.php) plus a full regression pass across every Overtime suite (257 tests) and the whole mcp test group (49 tests) - no regressions. Pint and Larastan clean.
<!-- SECTION:FINAL_SUMMARY:END -->
