---
id: KOL-138
title: Redesign the login page UI and translate it to Spanish
status: To Do
assignee: []
created_date: '2026-10-03 09:38'
labels:
  - module-auth
  - frontend
dependencies: []
ordinal: 152000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Improve the visual design of the employee login page (resources/js/pages/auth/login.tsx) and its auth layout, add the real Kolvi brand logo in place of the generic starter-kit icon (resources/js/components/app-logo-icon.tsx / app-logo.tsx), and translate every visible string (labels, placeholders, button text, links, page title) to Spanish. This is scoped to the login page only, not the broader Spanish i18n audit tracked in KOL-1.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Login page displays the Kolvi brand logo instead of the generic starter-kit icon
- [ ] #2 All visible text on the login page (field labels, placeholders, button, 'forgot password' link, remember-me, page title/description, status/flash messages) is in Spanish
- [ ] #3 Visual layout/spacing/styling is refreshed and reviewed in the browser by the user before merge
- [ ] #4 Change is scoped to the main employee login page; saas-login.tsx and dt-login.tsx are not touched unless the user asks
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
