---
id: KOL-29
title: Correct the stale technical assumptions in the reports PRD
status: To Do
assignee: []
created_date: '2026-08-04 11:16'
updated_date: '2026-08-04 19:00'
labels:
  - payroll-reports
  - docs
dependencies: []
documentation:
  - docs/prd-reports.md
priority: low
type: docs
ordinal: 28000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The reports PRD is sound on product, competitive research and requirements, but its technical section was written against assumptions that do not match this codebase. Anyone picking up a Fase 1 task and reading section 8 for guidance will be misled. The research and requirements sections need no changes.

What is wrong:
- **Section 8 is titled 'Consideraciones Tecnicas (Laravel Filament)'.** This app is Inertia v3 + React 19 with Tailwind v4. There is no Filament.
- Section 8 suggests adding `maatwebsite/excel`. The project already ships `phpoffice/phpspreadsheet`, `phpoffice/phpword` and `barryvdh/laravel-dompdf`, wired through `app/Services/Reports/DtReportExporter.php`, which renders one Blade fragment into all formats. No new dependency is needed.
- Section 8 says to reuse 'el motor de deteccion de anomalias' from the DT daily report. What actually exists is `workdays.status` (`app/Enums/WorkdayStatus.php`) plus pending `MarkModification` records — accurate enough in spirit, but worth naming precisely.
- Section 3 and section 6 claim the work is only presentation, validation and export over an existing calculation engine. **That is wrong for overtime**: `workdays.extra_time` is raw clock overflow computed in `app/Services/WorkdayCalculator.php`, with no authorisation, no pactada/no pactada distinction and no percentage buckets. KOL-11 and KOL-12 exist because of this.
- RF-7 filters by centro de costo and tipo de contrato, neither of which existed in the schema when the PRD was written. KOL-30 and KOL-10 add them.
- Section 8 suggests Sanctum for the tenant API. Sanctum is in use, but for employee device tokens on the mobile app, which is a different auth model — worth noting so Fase 3 does not assume the work is already half done.

Update the document in place and keep the original phasing and requirement numbering intact so the KOL tasks referencing RF numbers stay valid.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Section 8 no longer refers to Filament and describes the actual Inertia + React stack
- [ ] #2 The maatwebsite/excel suggestion is replaced by the existing phpspreadsheet, phpword and dompdf setup and a pointer to DtReportExporter
- [ ] #3 The claim that no recalculation is needed is corrected to name the overtime gap explicitly, referencing KOL-11 and KOL-12
- [ ] #4 The cost centre and contract type gaps are noted against RF-7, referencing KOL-30 and KOL-10
- [ ] #5 The Sanctum note distinguishes employee device tokens from the tenant API keys Fase 3 would need
- [ ] #6 RF numbering, phasing and the competitive research sections are left unchanged so existing task references stay valid
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
