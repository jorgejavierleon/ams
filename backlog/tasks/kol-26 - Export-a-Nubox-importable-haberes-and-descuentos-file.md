---
id: KOL-26
title: Export a Nubox-importable haberes and descuentos file
status: To Do
assignee: []
created_date: '2026-08-04 11:16'
labels:
  - payroll-reports
  - backend
  - frontend
  - integration
milestone: m-1
dependencies:
  - KOL-13
  - KOL-25
documentation:
  - docs/prd-reports.md
priority: high
type: feature
ordinal: 25000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
RF-4 and user story 3, the commercial point of the whole feature: the accountant receives a file Nubox imports directly, with no re-keying. Nubox is the most widely used accounting software among Chilean PYMEs, and PRD section 2.3.1 records that **GeoVictoria already ships a native Nubox integration** and markets it against the same Ley de 40 Horas compliance angle Kolvi uses. File export is the affordable way to reach parity now; the native API integration is deliberately deferred.

The target format is confirmed in PRD section 2.3.2 — Nubox's *Carga Masiva de Haberes y Descuentos Variables* (Utilitarios → Importar), a flat four-column table:

| Columna | Contenido |
|---|---|
| PERIODO | Mes y año, ej. 08/2026 |
| FUNCIONARIO | RUT o código del trabajador, matching the maestro already loaded in the client's Nubox |
| CODIGO DE HABER DESCUENTO | The concept code the client configured in their own Nubox |
| MONTO/DIAS/HORAS | The quantity for that concept |

One row per concept per worker per period: a worker with 50% overtime and an atraso produces two rows.

**The critical design constraint: Kolvi must not hardcode Nubox concept codes.** Every company configures its own haberes and descuentos, so the mapping from a Kolvi figure (horas extra 50%, atraso, día de ausentismo injustificado) to a Nubox code belongs to the client, configured through the KOL-25 template mechanism. Shipping fixed codes would produce files that import cleanly into the wrong concepts — worse than failing.

The template is a system template per RF-3: its four columns and their order are fixed, but the concept-code mapping within it is the client's.

Verify the exact file format Nubox's importer expects — extension, header row, delimiter, date and decimal formats — against the Nubox help centre article before finalising, and record what was verified in the notes. Getting a decimal separator wrong here silently misstates someone's pay.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The export produces the four Nubox columns exactly, one row per concept per worker per period
- [ ] #2 A worker with several concepts in one period produces one row per concept
- [ ] #3 Concept codes come from the client's configured mapping; no Nubox code is hardcoded anywhere in the codebase
- [ ] #4 The Nubox template ships as a non-editable system template whose column structure is fixed while the concept mapping stays the client's to set
- [ ] #5 A concept with no configured code is reported to the user before export rather than exported blank or with a guessed code
- [ ] #6 The period, RUT, quantity and decimal formats match what the Nubox importer expects, verified against the Nubox help centre and recorded in the notes
- [ ] #7 The integrity check runs before this export like any other, and the export is recorded in the audit history
- [ ] #8 A worker with no payroll-relevant concepts in the period produces no rows rather than zero-valued ones
- [ ] #9 Pest tests cover the multi-concept worker, the unmapped-concept case, the empty-worker case, and the exact column structure and formatting of the output
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
