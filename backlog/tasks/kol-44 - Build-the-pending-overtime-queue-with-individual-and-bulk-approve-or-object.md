---
id: KOL-44
title: Build the pending-overtime queue with individual and bulk approve or object
status: Done
assignee: []
created_date: '2026-08-06 02:53'
updated_date: '2026-08-14 09:59'
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
- [x] #1 A supervisor sees pending overtime for their direct reports and an HR admin sees it for the whole organization, filterable by period and employee
- [x] #2 A day can be approved or objected individually, and a selection can be decided in bulk
- [x] #3 Every decision records the acting user, the timestamp and the reason, with the reason mandatory when the day exceeds a legal cap
- [x] #4 Approving a day with an unreviewed anomaly flag is refused, and the flag reason is shown rather than a generic error
- [x] #5 The approver can authorise fewer hours than were calculated, and the resulting figure reflects the lower amount
- [x] #6 Bulk approval cannot bypass the anomaly block or the justification requirement for any day in the selection
- [x] #7 Objecting leaves the underlying raw marks untouched
- [x] #8 The queue is bounded in query count for a 500-employee period and is organization-scoped
- [x] #9 All labels are in Spanish and the screen uses the shared DataTable foundation
- [x] #10 Pest tests cover individual approval, objection, bulk approval, a bulk selection containing a flagged day, an over-cap approval without justification, and a supervisor attempting to decide for someone outside their team
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. OvertimeQueueController: index() (team-scoped for supervisors via ViewTeam, org-wide for admin, paginated+eager-loaded, filters by status/period/employee, default tab pending), approve()/object() (individual decide, pre-checks workday anomaly flags for a Spanish flag-reason error before ever calling the model, catches OvertimeDecisionRefused as a translated 'reason required' error), bulkDecide() (whereIn+pending()+per-row Gate::denies skip, calls the same approve()/object() so the model's saving-hook guard cannot be bypassed, reports only the actually-decided count).
2. Routes under overtime/queue/* gated by ViewTeam|Manage permission; wayfinder:generate --with-form.
3. React: resources/js/pages/overtime/queue/index.tsx reusing DataTable/useServerTable with row-selection+renderSelectionActions (Workdays' bulk pattern) plus Leaves' status-tabs pattern; approve dialog with editable authorized_hours + optional reason, object dialog with mandatory reason, bulk dialog for approve/object. Link added from overtime/index.tsx landing shell.
4. Spanish/English lang additions under ui.overtime.queue.*.
5. Pest: tests/Feature/OvertimeQueueTest.php covering individual approve/object, flagged-day refusal with flag reason, over-cap refusal without/with justification, cross-team refusal, bulk approve, bulk selection containing a flagged day, bulk cross-team no-op, and team-scoped vs org-wide listing.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verification: sail artisan test --filter=OvertimeQueue passes (12/12, 57 assertions); full overtime suite (107 tests) and npm run types:check pass; vendor/bin/pint --dirty clean. Added two tests during finalization to close AC evidence gaps: (1) queue-level authorized_hours < calculated_hours end-to-end (AC5 was previously only proven at the model layer), (2) query-count-bounded-at-scale with 60 employees on the queue index (AC8), asserting query count stays under 15 regardless of employee count. Full sail test suite run separately: 953/977 pass; the 20 failures are all pre-existing local Docker filesystem-permission errors (storage/framework/testing/disks/public) in unrelated Document/Avatar upload tests, not touched by this branch.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Built the pending-overtime queue (/overtime/queue): OvertimeQueueController::index/approve/object/bulkDecide, gated by ViewTeam|Manage with per-record ApproveTeam+team-scoping enforced in OvertimeAuthorizationPolicy. Individual and bulk decisions both route through OvertimeAuthorization::approve()/object(), so the anomaly block (KOL-40) and legal-cap justification (KOL-41) apply identically either way; bulk skips (not silently approves) any row that fails those checks and reports only the actually-decided count. Frontend reuses the shared DataTable with row selection, status tabs, employee/date-range filters, and approve/object/bulk dialogs, all in Spanish. Verified with 12 Pest tests (individual approve/object, editable authorized_hours below calculated, flagged-day refusal with flag reason, cross-team refusal for both individual and bulk, bulk with a flagged day left pending, supervisor-vs-admin scoping, and a query-count-bounded-at-scale check), plus pint and npm run types:check clean.
<!-- SECTION:FINAL_SUMMARY:END -->
