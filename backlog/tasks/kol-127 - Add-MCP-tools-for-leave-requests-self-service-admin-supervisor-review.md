---
id: KOL-127
title: Add MCP tools for leave requests (self-service + admin/supervisor review)
status: Done
assignee: []
created_date: '2026-09-24 19:32'
updated_date: '2026-09-25 09:34'
labels:
  - mcp
  - leave
  - backend
dependencies:
  - KOL-126
priority: high
type: feature
ordinal: 127000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Lets a user's agent do everything they can already do with leave requests in the web app — nothing more. Wraps the existing `My\LeaveController` (self-service) and `LeaveController`/`LeavePolicy` (admin/supervisor review) 1:1; no new leave behavior, just an agent-facing entry point to what already exists.

Self-service tools (gated by the employee's existing `RequestOwn:Leave`/`ViewOwn:Leave`/`CancelOwn:Leave` permissions):
- Create a leave request for myself (same type restrictions as today — Medical stays excluded from self-service per `LeaveType::selfServiceCases()`)
- View my own leave requests and their status
- Cancel a pending leave request of mine

Review tools (gated by `ViewTeam:Leave`/`ApproveTeam:Leave`, same supervisor-scoping via `supervisor_id` as `LeavePolicy` enforces today):
- View my team's leave requests
- Approve or reject a leave request (rejection requires a reason, matching existing validation)

Admin-initiated creation is new ground: `LeaveController::store` today is gated only by `role:admin` route middleware, not a permission. This ticket adds a `Create:Leave` Spatie permission, seeds it to the `admin` role, and retrofits `LeaveController::store` to `Gate::authorize` against it instead of relying on the route-level role check — so the MCP tool and the web controller share one real authorization path.

## User stories for manual testing (Gherkin)

Scenario: An employee's agent requests a leave on their behalf
  Given an employee has a valid MCP session
  When their agent calls the create-leave tool with valid dates and a self-serviceable type
  Then a pending leave request is created for that employee, visible in their leave history

Scenario: An employee's agent cannot request Medical leave
  Given an employee has a valid MCP session
  When their agent calls the create-leave tool with type Medical
  Then the tool refuses, same as the self-service web form does today

Scenario: A supervisor's agent approves a team member's leave
  Given a supervisor has a valid MCP session and one of their direct reports has a pending leave request
  When their agent calls the approve-leave tool for that request
  Then the request becomes approved and is no longer pending

Scenario: A supervisor's agent cannot approve a leave outside their team
  Given a supervisor has a valid MCP session
  When their agent calls the approve-leave tool for an employee who is not their direct report
  Then the tool refuses, same as LeavePolicy enforces in the web app today

Scenario: An admin's agent creates a leave on behalf of any employee
  Given an admin has a valid MCP session
  When their agent calls the create-leave tool specifying a target employee
  Then a leave request is created for that employee, gated by the new Create:Leave permission
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Tools exist for: create (self), view own, cancel own, view team, approve, reject, and create (admin, on behalf of an employee)
- [x] #2 Every tool's authorization matches the equivalent web action exactly: same permission, same policy, same supervisor scoping
- [x] #3 A new Create:Leave permission is added to RoleSeeder and granted to admin; LeaveController::store now authorizes via Gate::authorize instead of relying solely on role:admin route middleware
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
1. RoleSeeder: add `Create:Leave` to ADMIN_PERMISSIONS with a doc comment, seed + grant to admin.
2. Retrofit app/Http/Controllers/LeaveController.php::store() to call Gate::authorize('create', Leave::class) as its first line (Gate facade already imported). role:admin route middleware stays as defense-in-depth.
3. Add app/Mcp/Tools/Leave/ with 7 tools extending AuthorizedTool, each mirroring one existing controller action 1:1 (reusing Leave::create, LeaveManager, LeaveApprovers, LeavePolicy - no new domain logic):
   - CreateLeaveTool: self-service create, gate 'RequestOwn:Leave', excludes Medical (LeaveType::selfServiceCases()), mirrors My\LeaveController::store half-day forcing + notification.
   - ViewOwnLeavesTool: gate 'ViewOwn:Leave', lists the caller's own leaves (optional status/page filters) + vacation balance, mirrors My\LeaveController::index.
   - CancelLeaveTool: gate 'CancelOwn:Leave', ownership+pending check mirrors My\LeaveController::destroy abort_unless, then LeaveManager::delete().
   - ViewTeamLeavesTool: gate policy ability 'viewTeam' on Leave::class, admin-sees-all vs supervisor_id scoping mirrors LeaveController::index.
   - ApproveLeaveTool: gate policy ability 'approve' on the loaded Leave, abort_if already-approved, LeaveManager::approve() - mirrors LeaveController::approve.
   - RejectLeaveTool: gate policy ability 'reject', abort_if rejected/medical, required reason, LeaveManager::reject() - mirrors LeaveController::reject.
   - CreateLeaveForEmployeeTool: gate policy ability 'create' on Leave::class (new Create:Leave permission), org-scoped user_id validation, all LeaveType cases allowed, mirrors LeaveController::store including medical auto-approve notification skip.
   Each tool resolves the target Leave via org-scoped query (OrganizationScope already active) and returns Response::error for not-found/unauthorized/invalid-state, Response::structured([...]) on success.
4. Register all 7 tools in KolviServer::$tools.
5. Pest tests under tests/Feature/Mcp/Leave/ (one file per tool or grouped), uses()->group('mcp'), calling handle() directly with $this->actingAs() per the AuthorizedToolTest precedent - success + denial (wrong permission, wrong team, wrong state) path per tool.
6. vendor/bin/pint --dirty --format agent; run affected tests (new mcp/leave tests + existing LeaveManagementTest/LeaveRequestTest/SupervisorLeaveApprovalTest to confirm the store() retrofit causes no regression, since admin bypasses the new gate via the existing super-admin Gate::before).
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented all 7 tools under app/Mcp/Tools/Leave/, registered in KolviServer:
- create-leave, view-own-leaves, cancel-leave (self-service, gated on RequestOwn/ViewOwn/CancelOwn:Leave)
- view-team-leaves, approve-leave, reject-leave (gated via LeavePolicy viewTeam/approve/reject, same supervisor_id scoping as the web app)
- create-leave-for-employee (admin, gated via new LeavePolicy::create -> Create:Leave permission)

RoleSeeder: added Create:Leave to ADMIN_PERMISSIONS. LeaveController::store now calls Gate::authorize('create', Leave::class) as its first line (role:admin route middleware stays as defense-in-depth) - admin bypasses via the existing Gate::before super-admin rule, so no regression in existing admin-create tests.

Return type note: Response::structured() returns Laravel\Mcp\ResponseFactory, not Response, so every tool's handle() is typed Response|ResponseFactory (ResponseFactory does not extend Response).

Tests: tests/Feature/Mcp/Leave/LeaveToolsTest.php (24 tests, success+denial per tool, using the first-party KolviServer::actingAs()->tool() testing helper documented in laravel/mcp). Updated KolviServerTest's placeholder "no tools yet" assertion to list the 7 registered tool names.

Verified: pint clean; Larastan clean on all touched files (had to fix several Model::find()/findOrFail() int-cast ambiguities and swap fresh() for refresh() to satisfy static analysis); full regression pass on LeaveManagementTest, LeaveRequestTest, SupervisorLeaveApprovalTest, LeavesApiTest, LeaveCalendarTest, LeaveQueueBadgeTest, LeaveWorkdayRecalculationTest, AuthorizedToolTest, KolviServerTest - all passing, no regressions from the store() gate retrofit.

No UI in this ticket (backend/MCP only) - nothing for Phase 4.5 QA checklist.

Final verification before close: vendor/bin/pint --dirty --format agent -> passed. sail artisan test --compact --filter="LeaveToolsTest|KolviServerTest|AuthorizedToolTest|LeaveManagementTest|LeaveRequestTest|SupervisorLeaveApprovalTest|LeavesApiTest|LeaveCalendarTest|LeaveQueueBadgeTest|LeaveWorkdayRecalculationTest" -> 120 tests, 450 assertions, all passed. No TypeScript touched, so DoD #3 (npm run types:check) is not applicable.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added 7 MCP tools (app/Mcp/Tools/Leave/) wrapping the existing self-service and admin/supervisor leave controllers 1:1, gated on the same permissions/policies the web app already enforces. Added a new Create:Leave permission (RoleSeeder, granted to admin) and retrofitted LeaveController::store to Gate::authorize against it instead of relying solely on the role:admin route middleware. Verified with 24 new Pest tests (tests/Feature/Mcp/Leave/LeaveToolsTest.php, success+denial per tool) plus a full regression pass across every leave-related suite (120 tests total) - no regressions. Pint and Larastan clean.
<!-- SECTION:FINAL_SUMMARY:END -->
