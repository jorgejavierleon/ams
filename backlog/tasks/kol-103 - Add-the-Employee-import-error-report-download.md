---
id: KOL-103
title: Add the Employee import error-report download
status: To Do
assignee: []
created_date: '2026-09-03 20:45'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-102
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: medium
type: feature
ordinal: 90000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Lets a user see exactly what went wrong in a completed import. The CSV is written during ProcessImportRun's (KOL-102) commit pass itself, not generated later — this ticket adds that writing plus the download route and exact column format. Full format decision: KOL-94.8.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 During ProcessImportRun's commit pass, every ImportIssue encountered is appended to a CSV at import-runs/{organization_id}/{importRun}-errores.csv on the local disk (UTF-8 with BOM, comma-delimited), one line per issue; the path is stored on the ImportRun and the file is regenerated from scratch on every job attempt, including retries
- [ ] #2 CSV columns, in order: Fila (1-indexed row number including the header row), Columna (the field's human Spanish label, blank for whole-row issues), Severidad ('Advertencia' or 'Error'), Mensaje (the issue text)
- [ ] #3 GET imports/{importRun}/error-report is available once errored > 0, gated by Import:Employee and the run's organization, and streams the file via the same authenticated-disk-download pattern as report exports
- [ ] #4 Feature tests cover: a run with a mix of warnings and errors produces a CSV with the right row count, columns, and values in the right order; the route is unavailable when errored == 0; and a user outside the run's organization (or without Import:Employee) cannot download it
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
