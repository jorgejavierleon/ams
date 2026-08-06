---
id: KOL-41
title: >-
  Validate legal caps without blocking, and require a written justification to
  approve an excess
status: To Do
assignee: []
created_date: '2026-08-06 02:51'
updated_date: '2026-08-06 02:57'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies:
  - KOL-39
  - KOL-36
  - KOL-11
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 600
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PRD section 7.3. This answers the question KOL-11 left open as acceptance criterion 5, and the answer is **surface, do not block** — with a price attached.

Resolución 38 art. 45.2 is unambiguous that a legal-cap alert is advisory and never blocks the entry. GeoVictoria confirms the same operational reading from the market side: the platform allows authorising more than 2 hours in a day for exceptional cases such as critical-service continuity, showing a warning rather than refusing. A system that hard-blocks at 2 hours simply pushes the client into recording the truth somewhere Kolvi cannot see, which is worse for them in an audit than an authorised excess with a reason attached.

So: the caps from KOL-36 are validated at approval time, the excess is allowed, and **approving beyond a cap requires a written justification from the approver**. No justification, no approval. That justification is what makes the excess defensible later.

Caps to validate, all resolved for the date being approved rather than for today:
- 2 overtime hours per day (Código del Trabajo art. 31)
- 12 overtime hours per week
- ordinary plus extraordinary within the daily and weekly ceilings

Weekly caps mean this validation is not per-day in isolation: approving 2 hours on Friday can push the week over its limit even though that Friday is individually within bounds. The check therefore needs the week context, and the week the day belongs to has to be defined explicitly — write the rule and its reasoning into the notes, including what happens to a week straddling a month boundary, since KOL-24 will depend on the same definition.

Warning at the point of *entry* (loading a schedule that would exceed the caps) is also required by art. 45.2, and is likewise non-blocking.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The daily overtime cap, the weekly overtime cap and the combined ordinary-plus-extraordinary ceilings are evaluated at approval time using the limits in force on the date being approved
- [ ] #2 Exceeding a cap never prevents saving a mark, saving a shift or calculating a day, per Resolución 38 art. 45.2
- [ ] #3 Approving hours beyond a cap is possible, but only when the approver supplies a written justification; approval without one is refused
- [ ] #4 The justification is stored against the approval and is retrievable for audit alongside who approved and when
- [ ] #5 The weekly evaluation accounts for hours already approved earlier in the same week, so an individually valid day that pushes the week over its limit is caught
- [ ] #6 The definition of the week used, and the handling of a week straddling a period boundary, is documented in the notes with its reasoning
- [ ] #7 Loading a schedule that would exceed the caps produces an on-screen advisory warning that does not block the save
- [ ] #8 Pest tests cover a day within caps, a day over the daily cap, a week pushed over by an individually valid day, approval rejected for a missing justification, approval accepted with one, and a cap change between the worked date and the approval date
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
