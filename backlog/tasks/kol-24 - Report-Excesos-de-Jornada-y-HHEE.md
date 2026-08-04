---
id: KOL-24
title: 'Report: Excesos de Jornada y HHEE'
status: To Do
assignee: []
created_date: '2026-08-04 11:15'
labels:
  - payroll-reports
  - backend
  - frontend
  - report
milestone: m-0
dependencies:
  - KOL-12
  - KOL-15
  - KOL-19
documentation:
  - docs/prd-reports.md
priority: medium
type: feature
ordinal: 23000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1's overtime report, at either individual or consolidated level. This is where the domain work from KOL-11 and KOL-12 becomes visible to the user: overtime **by week**, with each block marked pactada or no pactada and split into its pay buckets.

Weekly rather than daily grouping is deliberate and is not a display preference — Chilean overtime limits are framed per day (art. 31) while the ordinary jornada is capped per week, and the 40-hour law makes the weekly view the one an employer is actually managing against. Buk ships a 'reporte de excesos de jornada semanal' for the same reason. Confirm how the current weekly hour cap applies for the tenants Kolvi serves before fixing the threshold in code, and put the finding in the notes.

The report has two audiences and both matter: RRHH checking nobody is drifting into unauthorised overtime, and the accountant needing the payable total per bucket. Make sure unauthorised hours are prominent rather than a footnote — they are the ones that are not going to be paid, and the ones that create legal exposure if they keep appearing.

Formats: Excel and PDF per RF-1.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Overtime is presented grouped by week for a single employee or consolidated across a selection
- [ ] #2 Each entry is marked pactada or no pactada and split into its pay buckets, using the classifier rather than raw extra_time
- [ ] #3 Unauthorised overtime is visually prominent and separated from payable totals
- [ ] #4 Weeks exceeding the applicable statutory limit are flagged, with the limit confirmed and its basis recorded in the notes
- [ ] #5 Weeks that straddle a period boundary are handled by an explicit documented rule
- [ ] #6 The report exports to Excel and PDF matching the screen
- [ ] #7 The export is recorded in the export audit history
- [ ] #8 All labels are in Spanish
- [ ] #9 Pest tests cover authorised-only, unauthorised-only and mixed weeks, a week over the limit, and the boundary case
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
