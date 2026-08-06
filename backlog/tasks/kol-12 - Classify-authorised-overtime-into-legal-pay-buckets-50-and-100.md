---
id: KOL-12
title: Classify authorised overtime into legal pay buckets (50% and 100%)
status: To Do
assignee: []
created_date: '2026-08-04 11:11'
updated_date: '2026-08-06 02:56'
labels:
  - payroll-reports
  - backend
  - domain
milestone: m-0
dependencies:
  - KOL-11
  - KOL-46
  - KOL-49
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 11000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-1 requires the payroll summary to break overtime into 'HHEE 50%, HHEE 100% según corresponda'. Today `workdays.extra_time` is one undifferentiated time column — there is no bucketing anywhere in the codebase.

In Chile the recargo on horas extraordinarias is 50% over the ordinary hourly rate (Código del Trabajo art. 32). The '100%' bucket clients ask for is not a second overtime rate — it is how many payroll systems encode work performed on a Sunday or festivo for workers whose weekly rest falls there (art. 38), which carries its own compensation rules. **Confirm this reading before implementing**; if the correct modelling is 'domingo/festivo trabajado' as a distinct concept rather than an overtime percentage, model it that way and name it accordingly. The point is that the payroll summary must expose the buckets an accountant needs, not that there must literally be a field called 100%.

The inputs already exist: `workdays` carries the date, worked and extra time; `app/Models/Holiday.php` and the seeder cover festivos; `app/Services/Reports/SundaysReportService.php` already reasons about Sunday work for the DT report and is worth reading before writing anything new. KOL-11 supplies which hours are authorised.

Produce a service that, for an employee over a date range, returns the classified breakdown. It must not mutate `workdays` — this is a read-side derivation, so a recalculation of attendance never silently changes historical payroll figures.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A service returns, for an employee and date range, overtime split into its pay buckets plus an unauthorised bucket, derived from workdays without writing to them
- [ ] #2 Sunday and holiday work is identified using the existing Holiday data and produces the correct bucket for the reading confirmed in the description
- [ ] #3 The interpretation of the 50/100 buckets is confirmed against Código del Trabajo art. 32 and 38 and written into the task notes before the implementation is finalised
- [ ] #4 Only hours authorised per KOL-11 land in a payable bucket; unauthorised excess is reported separately and never merged into a payable total
- [ ] #5 The breakdown totals reconcile exactly with the sum of extra_time over the period, so nothing is lost or double-counted
- [ ] #6 Recalculating a workday after the fact does not alter a previously exported classification silently; the behaviour on recalculation is tested
- [ ] #7 Pest tests cover a normal weekday, a Sunday, a holiday, a partially authorised day, and a fully unauthorised day
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @planning
created: 2026-08-06 02:56
---
Scope note from the overtime module planning (m-2): KOL-11 has been rescoped to just the authorisation record, and the wider overtime work now lives in milestone m-2 "Módulo de Horas Extras (HHEE)". This task should read the approved-only export dataset from KOL-49 rather than reaching into overtime records or `workdays.extra_time` directly — KOL-49 already resolves day type (weekday / Sunday / holiday) from the existing Holiday data and the SundaysReportService reasoning, and already excludes rest-day-compensated and unauthorised hours. The unauthorised bucket this task must report separately comes from KOL-46.
---
<!-- COMMENTS:END -->
