---
id: KOL-98
title: Add the Employee import upload step with template download
status: To Do
assignee: []
created_date: '2026-09-03 20:44'
labels:
  - bulk-import
milestone: m-3
dependencies:
  - KOL-97
references:
  - backlog/tasks/kol-94 - Bulk-data-import-framework-map.md
priority: high
type: feature
ordinal: 85000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
First user-facing slice of the Employee import wizard specced in KOL-94: the ImportWizardController is introduced with its template-download and upload (store) routes, and the wizard shell renders the first step. Depends on KOL-97's ImportRun model and permission. Full route contract: KOL-94.5 (addendum in KOL-94.8 for the template route); threshold rationale: KOL-94.1.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 GET imports/employee/template/{format} (excel|csv), gated by Import:Employee, downloads a template built from EmployeeImportSchema's field order with human Spanish labels and no example data row; an unlisted format 404s
- [ ] #2 POST imports/employee accepts an uploaded file, validates its real format via IOFactory::identify()/createReaderForFile() (not the file extension), and rejects anything else
- [ ] #3 A valid upload creates an ImportRun scoped to the acting user's organization_id, sets expires_at from a config-driven TTL, and transitions Pending -> MappingReview
- [ ] #4 An upload whose row count exceeds the sync-preview threshold (separate configurable CSV/Xlsx numbers per KOL-94.1) is rejected immediately with a validation error, never queued or partially previewed
- [ ] #5 GET imports/{importRun} (show) renders per the ImportRun's current status; unauthorized users and users outside the ImportRun's organization get a 403/404
- [ ] #6 Feature tests cover: a valid upload reaches MappingReview, an over-threshold file is rejected, a renamed-extension file is rejected, a user without Import:Employee gets 403, and the template downloads with the expected header row and order
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
