---
id: KOL-11
title: Model overtime authorisation so HHEE can be split into pactada and no pactada
status: To Do
assignee: []
created_date: '2026-08-04 11:10'
labels:
  - payroll-reports
  - backend
  - frontend
  - domain
milestone: m-0
dependencies: []
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 10000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
**This is the single biggest hidden gap between the PRD and the codebase.** The PRD claims the payroll work is only a presentation/validation/export layer over an existing calculation engine. That is true for horas trabajadas, atrasos and ausentismos — but not for overtime.

Today `workdays.extra_time` is computed in `app/Services/WorkdayCalculator.php` as raw clock overflow: a SQL `CASE` that subtracts the shift's scheduled duration from the actual in-to-out span and stores whatever is left over. Nothing distinguishes overtime the employer agreed to from an employee who simply lingered past their shift. No employer in Chile pays the second one, and no accountant will trust an export that conflates them.

RF-1 lists a report of 'Excesos de Jornada / HHEE ... con detalle de justificación (pactada/no pactada)' and RF-4 expects overtime to arrive at Nubox as a specific haber code. Both need this distinction to exist as data.

Build the authorisation record itself here; the classification of authorised hours into legal percentage buckets is a separate task that depends on this one.

Legal context worth reading before designing: in Chile overtime must be agreed in writing, is capped at 2 hours per day, and may only cover transitory needs (Código del Trabajo art. 31-32). The model should make it possible to represent an agreement covering a date range as well as an ad-hoc approval of a specific day, because both happen in practice. Approval flow can follow the shape already used for leave and mark modifications (`app/Models/MarkModification.php`, `app/Http/Controllers/MarkModificationReviewController.php`, `app/Enums/MarkModificationStatus.php`) rather than inventing a new pattern.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 An overtime authorisation model exists that can express both a standing agreement over a date range and an approval for a single day, for a given employee
- [ ] #2 Each authorisation records who approved it, when, and how many hours are covered, so an export can be traced back to a human decision
- [ ] #3 Given a workday with extra_time, the system can answer whether those hours are authorised and how many of them are, without recomputing attendance
- [ ] #4 Hours worked beyond a day's authorised amount remain visible as unauthorised rather than being silently dropped or silently paid
- [ ] #5 The daily 2-hour statutory ceiling is enforced or explicitly surfaced as a warning; whichever is chosen is documented in the notes with its reasoning
- [ ] #6 Authorisations are managed from the UI by users with the right permission, including approving and revoking, in Spanish
- [ ] #7 The model is organization-scoped and a tenant can never see or approve another tenant's overtime
- [ ] #8 Pest tests cover the range and single-day cases, the partial-authorisation case (worked 3h, authorised 2h), and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
