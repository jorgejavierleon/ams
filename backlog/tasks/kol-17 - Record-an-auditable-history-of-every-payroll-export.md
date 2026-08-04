---
id: KOL-17
title: Record an auditable history of every payroll export
status: To Do
assignee: []
created_date: '2026-08-04 11:12'
labels:
  - payroll-reports
  - backend
  - frontend
milestone: m-0
dependencies: []
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 16000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-6 and user story 5: a tenant admin must be able to see which reports were generated, by whom and when — for internal audit, and potentially as evidence toward the DT.

Required fields per the PRD: user, timestamp, report type, period consulted, format, and the filters applied. KOL-14 adds one more that matters: whether the export was confirmed over an integrity warning, and what that warning contained. An export that went out with 12 unresolved days is exactly the one someone will later need to explain.

Do not build a parallel audit mechanism. `spatie/laravel-activitylog` is already a dependency, `app/Models/Concerns` and `resources/js/components/activity-timeline.tsx` show how this project already records and renders activity, and section 8 of the PRD explicitly says to extend the existing log rather than duplicate it. Confirm during implementation whether the activity log's shape carries the structured filter payload well enough, or whether a dedicated table is genuinely warranted; if you deviate, record the reason.

Visibility requirement is specific and easy to get wrong: the log must be readable by the **tenant admin**, not only by superadmin.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Every payroll export records user, timestamp, report type, period, format and the filters that were applied
- [ ] #2 An export that proceeded past an integrity warning records that fact and what was unresolved at the time
- [ ] #3 The existing activity log is extended rather than a parallel audit system being built, or the deviation is justified in the notes
- [ ] #4 A tenant admin can view their organization's export history from the UI, in Spanish, without superadmin access
- [ ] #5 The history is organization-scoped; a test proves one tenant's exports are never visible to another
- [ ] #6 Both synchronous and queued exports are recorded, including exports whose job later failed
- [ ] #7 The history view is paginated and filterable by date and report type, using the existing DataTable pattern
- [ ] #8 Pest tests cover the recorded fields, the warned-and-confirmed case, tenant isolation, and admin visibility
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
