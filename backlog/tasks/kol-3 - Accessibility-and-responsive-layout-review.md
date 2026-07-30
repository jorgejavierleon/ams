---
id: KOL-3
title: Accessibility and responsive layout review
status: To Do
assignee: []
created_date: '2026-07-30 10:13'
labels:
  - module-auth
dependencies:
  - KOL-1
references:
  - 'https://github.com/jorgejavierleon/ams/issues/56'
ordinal: 3000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Migrated from GitHub issue #56.

The new React app must be usable on desktops used by HR admins and tablets/phones used by inspectors in the field. This is a final review pass to ensure all pages are accessible (keyboard navigation, screen reader labels) and responsive (mobile breakpoints for the Admin sidebar and DT reports tables).

## Technical Notes

### Frontend
- shadcn/ui components are accessible by default (Radix UI primitives) — focus on custom components
- Check `AdminLayout.tsx` sidebar for mobile breakpoint handling
- Add `aria-label` to icon buttons (edit, delete, copy) throughout
- Use `role="status"` on loading skeletons
- DT tables: wrap in `overflow-x-auto` container

## Dependencies
Blocked by KOL-1 (i18n) — all strings must be final before the a11y audit, since `aria-label` text is itself user-facing copy.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Admin sidebar collapses to a hamburger menu on screens < 768px
- [ ] #2 All form inputs have associated <label> elements or aria-label attributes
- [ ] #3 All interactive elements are reachable and operable via keyboard (Tab, Enter, Space, Escape)
- [ ] #4 Status badges and icon-only buttons have aria-label or title attributes
- [ ] #5 DT report tables scroll horizontally on small screens without layout breakage
- [ ] #6 Color is not the sole indicator of information (status badges also have text labels)
- [ ] #7 Focus ring is visible on all interactive elements (not suppressed by CSS)
- [ ] #8 Tested on Chrome mobile emulation for: Employees list, Workdays list, DT Attendance report
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
