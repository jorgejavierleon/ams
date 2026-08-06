---
id: KOL-45
title: Let employees request overtime and supervisors decide it (Mode A)
status: To Do
assignee: []
created_date: '2026-08-06 02:53'
labels:
  - overtime
  - backend
  - frontend
milestone: m-2
dependencies:
  - KOL-11
  - KOL-37
  - KOL-43
  - KOL-44
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 1000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Mode A from PRD section 7.1, the Talana-shaped flow: the employee requests the overtime *before* working it, the supervisor approves or rejects, and only then does the system count it. The PRD calls this the most airtight mode against marking errors, because an unrequested excess never had anyone intending it.

Scope of the request:
- An employee requests hours for a date — same day, a future date, or a past date when the tenant allows it. The retroactive window is the tenant setting from KOL-37; a request outside it is refused with a Spanish message stating the window.
- The request carries the hours asked for and, optionally, why.
- The supervisor approves or rejects it, and the employee sees the outcome and their own history.
- A rejected or unanswered request does not stop the employee from working — it stops those hours from being payable, which is the entire point.

This is web console only for now. The mobile app ships on its own release cycle and its `/api/v1` contract is better added once the domain has settled; a follow-up task covers that.

The employee-facing side mirrors the leave request flow closely enough to copy its shape: `app/Http/Controllers/My/LeaveController.php`, the routes under the `my` prefix in `routes/web.php` gated by `permission:RequestOwn:Leave`, and `resources/js/pages/my/leaves`. The supervisor side lands in the same queue as KOL-44 rather than a second inbox — a supervisor should have one place to look, whether the hours arrived as a request or as an unrequested excess.

Only relevant when the tenant runs pre-authorisation or combined mode; under pure post-hoc the request UI is not shown at all.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 An employee can request overtime hours for a given date, with an optional reason, from the web console in Spanish
- [ ] #2 Requests are accepted for the same day and future dates, and for past dates only within the tenant retroactive window; a request outside the window is refused with a message stating the window
- [ ] #3 The supervisor decides requests in the same queue used for unrequested excess, not in a separate inbox
- [ ] #4 The employee sees their own request history and current status, and no one else requests
- [ ] #5 A request that is rejected or never answered never produces payable hours, and never prevents the employee from working or marking
- [ ] #6 The request UI is hidden entirely for tenants running pure post-hoc mode
- [ ] #7 Requests are organization-scoped and an employee can only request for themselves
- [ ] #8 Pest tests cover a same-day request, a future request, a retroactive request inside and outside the window, a rejection, an unanswered request producing no payable hours, and the UI being absent under post-hoc mode
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
