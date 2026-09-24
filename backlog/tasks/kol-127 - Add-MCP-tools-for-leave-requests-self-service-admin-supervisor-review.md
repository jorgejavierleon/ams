---
id: KOL-127
title: Add MCP tools for leave requests (self-service + admin/supervisor review)
status: To Do
assignee: []
created_date: '2026-09-24 19:32'
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
- [ ] #1 Tools exist for: create (self), view own, cancel own, view team, approve, reject, and create (admin, on behalf of an employee)
- [ ] #2 Every tool's authorization matches the equivalent web action exactly: same permission, same policy, same supervisor scoping
- [ ] #3 A new Create:Leave permission is added to RoleSeeder and granted to admin; LeaveController::store now authorizes via Gate::authorize instead of relying solely on role:admin route middleware
- [ ] #4 Pest tests cover each tool's success and denial paths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
