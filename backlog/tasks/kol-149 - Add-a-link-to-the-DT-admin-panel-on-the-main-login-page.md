---
id: KOL-149
title: Add a link to the DT admin panel on the main login page
status: To Do
assignee: []
created_date: '2026-10-11 09:33'
labels: []
dependencies: []
references:
  - >-
    GeoVictoria login page screenshot (shared by user) showing an 'Acceso
    Dirección del Trabajo' pill button below the main login card
ordinal: 175000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The main employee/admin login page (resources/js/pages/auth/login.tsx) has no way to discover the separate Dirección del Trabajo inspector panel login (named route dt.login, page resources/js/pages/auth/dt-login.tsx). GeoVictoria solves this with a bordered pill-style button below the main login card labeled 'Acceso Dirección del Trabajo' that links to their DT-equivalent login. Add an equivalent link on our login page, styled with our own design system (not a copy of GeoVictoria's visual style), that routes to dt.login.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The main login page shows a visible link/button to the DT panel login
- [ ] #2 The link navigates to the dt.login named route via Wayfinder
- [ ] #3 Styling matches this app's existing design system (colors, typography, spacing) rather than mimicking GeoVictoria's screenshot
- [ ] #4 Link text and any new UI strings are translated in both lang/en/ui.php and lang/es/ui.php
- [ ] #5 Link is visible and usable on mobile viewport widths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
