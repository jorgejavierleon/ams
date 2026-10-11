---
id: KOL-149
title: Add a link to the DT admin panel on the main login page
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-10-11 09:33'
updated_date: '2026-10-11 11:03'
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
- [x] #1 The main login page shows a visible link/button to the DT panel login
- [x] #2 The link navigates to the dt.login named route via Wayfinder
- [x] #3 Styling matches this app's existing design system (colors, typography, spacing) rather than mimicking GeoVictoria's screenshot
- [x] #4 Link text and any new UI strings are translated in both lang/en/ui.php and lang/es/ui.php
- [x] #5 Link is visible and usable on mobile viewport widths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add bordered pill-style outline Button (asChild+Link) below the main login Form in login.tsx, linking to dt.login via the @/routes/dt Wayfinder export.
2. Add 'auth.dt_login_link' translation key to lang/en/ui.php and lang/es/ui.php, consumed via useTranslations().
3. Run npm run types:check and vendor/bin/pint --dirty.
4. No PHP logic changed; existing AuthenticationTest/DtLoginTest coverage for the dt.login route already exists, so no new Pest test needed.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified in browser via Chrome DevTools MCP: desktop (login page renders outline pill button 'Acceso Dirección del Trabajo' below the Iniciar sesión button) and 375x667 mobile viewport (button remains visible/usable). A11y snapshot confirmed the link resolves to http://localhost/dt/login and clicking it navigates there. npm run types:check, eslint, and vendor/bin/pint --dirty --format agent all pass clean. sail artisan test --compact --filter=AuthenticationTest (5 passed, 1 skipped) and --filter=DtLoginTest (16 passed) both green. DoD #4 (Pest test per PHP change) does not apply: no PHP logic changed, only translation string arrays in lang/*/ui.php; the dt.login route is already covered by DtLoginTest. code-review skill (medium) ran clean with zero findings.

Follow-up UX revision after user feedback: the pill-shaped outline Button rendered inside the Card, right under the primary submit button — too prominent, too close, and used a rounded-full shape the app's design system doesn't use. Moved the link to LoginLayout (resources/js/layouts/auth/login-layout.tsx), rendered below the Card entirely as a plain muted-foreground text link (no border/background), matching secondary-link conventions elsewhere. Re-verified in browser (desktop + mobile) and via types:check/eslint/pint/AuthenticationTest.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a link to the DT panel login below the main login card. It renders in LoginLayout (resources/js/layouts/auth/login-layout.tsx) as a plain, muted-foreground text link outside the Card — not a bordered button — so it stays de-emphasized relative to the primary 'Iniciar sesión' action while remaining discoverable. Links to the dt.login route via the Wayfinder @/routes/dt export. Added 'auth.dt_login_link' to lang/en/ui.php and lang/es/ui.php, consumed via useTranslations(). Verified with types:check, eslint, pint, the Authentication/DtLogin Pest suites, and a live browser check (desktop + 375px mobile viewport; link resolves to and navigates to /dt/login).
<!-- SECTION:FINAL_SUMMARY:END -->
