---
id: KOL-44
title: Build the pending-overtime queue with individual and bulk approve or object
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
  - KOL-40
  - KOL-41
  - KOL-43
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 900
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.5 and Mode B in section 7.1. This is the screen where the module earns its purpose: every calculated overtime day arrives here as pending, and nothing leaves for payroll until a person has approved or objected to it.

Buk calls the negative decision *objetar*, and the distinction from a simple rejection matters — an objection says the excess happened but is not payable, which is a different statement from denying that it happened. The raw marks stay untouched either way.

What the queue does:
- Lists pending overtime for the supervisor team or, for HR, the whole organization, filterable by period and employee.
- Approves or objects individually, and in bulk, because a month-end review of a 200-person site is not a one-at-a-time job.
- Records the acting user, the timestamp and the reason on every decision. The reason is optional in general and mandatory when the day exceeds a legal cap (KOL-41).
- Refuses to approve a day carrying an unreviewed anomaly flag (KOL-40), showing the flag reason instead of a generic error, so the reviewer knows to go fix the mark first.
- Surfaces the authorised figure as editable — a supervisor approving 2 hours of a calculated 3 is the normal case, not an exception, and is exactly what makes the final figure defensible.

Bulk approval needs care in two places: it must not be a way to bypass the anomaly block or the justification requirement, and it must stay bounded in query count for a large selection.

Reuse the shared server-driven DataTable foundation used by every other list screen in this app rather than building a new table. Spanish throughout. Permissions and team scoping come from KOL-43.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A supervisor sees pending overtime for their direct reports and an HR admin sees it for the whole organization, filterable by period and employee
- [ ] #2 A day can be approved or objected individually, and a selection can be decided in bulk
- [ ] #3 Every decision records the acting user, the timestamp and the reason, with the reason mandatory when the day exceeds a legal cap
- [ ] #4 Approving a day with an unreviewed anomaly flag is refused, and the flag reason is shown rather than a generic error
- [ ] #5 The approver can authorise fewer hours than were calculated, and the resulting figure reflects the lower amount
- [ ] #6 Bulk approval cannot bypass the anomaly block or the justification requirement for any day in the selection
- [ ] #7 Objecting leaves the underlying raw marks untouched
- [ ] #8 The queue is bounded in query count for a 500-employee period and is organization-scoped
- [ ] #9 All labels are in Spanish and the screen uses the shared DataTable foundation
- [ ] #10 Pest tests cover individual approval, objection, bulk approval, a bulk selection containing a flagged day, an over-cap approval without justification, and a supervisor attempting to decide for someone outside their team
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
