---
id: KOL-150
title: Style the DT panel login page to match the main login page
status: To Do
assignee: []
created_date: '2026-10-11 09:35'
updated_date: '2026-10-11 09:35'
labels: []
dependencies: []
ordinal: 176000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The DT (Dirección del Trabajo) inspector login page (resources/js/pages/auth/dt-login.tsx) currently renders through GuestLayout/AuthSimpleLayout because resources/js/app.tsx only special-cases the exact page name 'auth/login' for LoginLayout, and dt-login falls into the generic 'auth/' prefix branch. As a result it looks visually inconsistent with the main login page: no card container, bg-background instead of bg-muted, and a small icon-only logo (AppLogoIcon) instead of the full logo mark (AppLogoMark) used on the main login. Give the DT login page the same card-based chrome, background, and logo as the main login page so both feel like the same product, while keeping its DT-specific title, description, and form fields.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 DT login page (auth/dt-login) renders inside the same card-based layout as the main login page (centered Card, bg-muted page background)
- [ ] #2 DT login page shows the full app logo mark used on the main login page, not the small icon-only logo
- [ ] #3 DT-specific title ('AMS – Portal DT') and description text are preserved
- [ ] #4 DT login form fields, labels, and the 'Solicitud clave' link keep working after the layout change
- [ ] #5 Page is visually consistent with the main login page on mobile viewport widths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
