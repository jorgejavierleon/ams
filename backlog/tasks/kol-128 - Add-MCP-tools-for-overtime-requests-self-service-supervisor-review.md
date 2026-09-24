---
id: KOL-128
title: Add MCP tools for overtime requests (self-service + supervisor review)
status: To Do
assignee: []
created_date: '2026-09-24 19:32'
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
- [ ] #1 Tools exist for: create (self), view own, view team, approve, reject
- [ ] #2 Every tool's authorization matches the equivalent web action exactly: same permission, same policy, same supervisor scoping
- [ ] #3 No admin-initiated "create on behalf of" tool is added, since no such capability exists in the web app today
- [ ] #4 Pest tests cover each tool's success and denial paths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
