---
id: KOL-4
title: Improve UI/UX of employee view and update pages
status: To Do
assignee: []
created_date: '2026-07-30 10:13'
updated_date: '2026-07-30 10:14'
labels: []
dependencies: []
references:
  - 'https://github.com/jorgejavierleon/ams/issues/75'
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Migrated from GitHub issue #75.

**Needs refinement before implementation.** Captured on GitHub as a one-line reminder with no description. The intent is a design/usability pass over the employee **view** and **update** screens, but the specifics were never written down. Walk both pages and record concrete, checkable problems as acceptance criteria before starting — otherwise 'done' is undefined.

## Technical Notes

### Frontend
- Pages live under `resources/js/pages/` — locate the employee show/edit components before scoping
- Reuse existing components from `resources/js/components/` rather than adding new ones
- Related recent work: GitHub #74 added the employee avatar group on the Cargos list, which may set the visual direction to match

Overlaps KOL-3 (accessibility and responsive review), which will touch the same screens. Consider scoping this one first so KOL-3 audits the final layout.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Concrete, checkable acceptance criteria have been defined for this task (walk the employee show and edit pages and record real problems) — replace this criterion before implementing
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
