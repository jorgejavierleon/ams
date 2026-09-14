---
id: KOL-29
title: Correct the stale technical assumptions in the reports PRD
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-08-04 11:16'
updated_date: '2026-09-14 10:11'
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
- [x] #1 Section 8 no longer refers to Filament and describes the actual Inertia + React stack
- [x] #2 The maatwebsite/excel suggestion is replaced by the existing phpspreadsheet, phpword and dompdf setup and a pointer to DtReportExporter
- [x] #3 The claim that no recalculation is needed is corrected to name the overtime gap explicitly, referencing KOL-11 and KOL-12
- [x] #4 The cost centre and contract type gaps are noted against RF-7, referencing KOL-30 and KOL-10
- [x] #5 The Sanctum note distinguishes employee device tokens from the tenant API keys Fase 3 would need
- [x] #6 RF numbering, phasing and the competitive research sections are left unchanged so existing task references stay valid
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
1. Section 3: append a note correcting the 'no recalculation needed' claim for overtime, naming workdays.extra_time as raw clock overflow and pointing to KOL-11/KOL-12 as the work that built authorisation + bucketing.
2. RF-7 (section 6): add a note that cost centre and contract type filters didn't exist when written and were added by KOL-30 and KOL-10.
3. Section 8: retitle away from 'Laravel Filament', rewrite each bullet to match the actual stack (Inertia v3 + React 19), replace maatwebsite/excel with the existing phpspreadsheet/phpword/dompdf + DtReportExporter, replace the anomaly-engine reference with workdays.status (WorkdayStatus) + pending MarkModification records, and split the Sanctum note into employee device tokens (mobile, existing) vs a tenant/API-key model Fase 3 would still need to build.
4. Leave RF numbering, phasing (section 11) and sections 1-2 untouched.
5. No code changes -> DoD items 1-4 (pint/tests/types/pest) are not applicable; verify via backlog task-finalization guidance.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Docs-only change to docs/prd-reports.md. Verified against code before editing: composer.json confirms phpoffice/phpspreadsheet, phpoffice/phpword, barryvdh/laravel-dompdf (no maatwebsite/excel) wired through app/Services/Reports/DtReportExporter.php; app/Services/WorkdayCalculator.php confirms workdays.extra_time is raw clock overflow; app/Enums/WorkdayStatus.php confirms the regular/irregular/absent/incomplete/justified enum; app/Models/User.php + app/Http/Controllers/Api/TokenController.php confirm Sanctum is used for mobile device tokens, not a tenant API-key model. Confirmed KOL-11/KOL-12/KOL-30/KOL-10 are Done and match the PRD's claims via 'backlog task view'. DoD items 1-4 (pint/tests/types/pest) are not applicable — no PHP or TS was touched.

code-review (mattpocock-skills:code-review) caught that my first draft of §8's anomaly-validation bullet overcorrected: it claimed no anomaly-detection engine exists, but App\Enums\AnomalyFlagReason + workdays.anomaly_flags (computed by WorkdayCalculator::calculateAnomalyFlags()) do exist, built for overtime-authorization gating (KOL-11/KOL-12), not for the DT daily report. Fixed §8 to say so precisely, and fixed a minor mis-citation in §3 (the 50/100 HHEE split is specified in §5.1, not RF-1). DoD items 1-4 (pint/tests/types/pest) checked as vacuously satisfied — no PHP or TS was touched by this docs-only change.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Rewrote PRD section 8 (was 'Laravel Filament') to describe the real Inertia v3 + React 19 stack, replaced the maatwebsite/excel suggestion with the phpspreadsheet/phpword/dompdf setup already wired through DtReportExporter, and corrected the anomaly-detection claim to name the real mechanism (AnomalyFlagReason + workdays.anomaly_flags, built for overtime authorisation gating per KOL-11/KOL-12) and clarify it does not feed the DT daily report. Added a note to section 3 naming the overtime calculation gap (workdays.extra_time as raw overflow) and crediting KOL-11/KOL-12, and RF-4/§5.1 for the 50/100 split requirement. Added a note to RF-7 crediting KOL-30/KOL-10 for cost centre and contract type. Split the Sanctum note into mobile device tokens (existing) vs the tenant API-key model Fase 3 still needs. RF numbering, phasing and research sections left untouched. Verified each factual claim against the current codebase and referenced KOL tickets; a code-review pass caught and fixed an overcorrection in the anomaly-engine claim before finalizing.
<!-- SECTION:FINAL_SUMMARY:END -->
