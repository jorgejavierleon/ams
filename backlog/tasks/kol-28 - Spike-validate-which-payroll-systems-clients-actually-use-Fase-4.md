---
id: KOL-28
title: 'Spike: validate which payroll systems clients actually use (Fase 4)'
status: To Do
assignee: []
created_date: '2026-08-04 11:16'
labels:
  - payroll-reports
  - spike
dependencies: []
documentation:
  - docs/prd-reports.md
priority: low
type: spike
ordinal: 27000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Placeholder for Fase 4, which PRD section 11 marks explicitly as **not committed**. Native integrations with Buk, Talana or Rankmi are estimated at 4-6 weeks each, and PRD section 10 says outright that this must not be started before real client demand is validated.

The question is narrow: of Kolvi's actual clients and live prospects, which payroll system does each one use today? Nubox is already assumed dominant in the PYME segment and is handled by KOL-26. If the remainder splits thinly across several systems, the right answer is probably a well-documented generic export via KOL-25 rather than any native integration at all.

This exists so the option stays visible and does not get built on a hunch. It should stay closed until there is evidence.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The payroll system in use is recorded for each current client and active prospect
- [ ] #2 A recommendation is written on whether any native integration is worth building, and if so which one first
- [ ] #3 If no integration clears the bar, that conclusion is recorded and the task closed without development
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
