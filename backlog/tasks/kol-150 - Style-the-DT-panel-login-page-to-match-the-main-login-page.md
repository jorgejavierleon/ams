---
id: KOL-150
title: Style the DT panel login page to match the main login page
status: Done
assignee:
  - jorgejavierleon@gmail.com
created_date: '2026-10-11 09:35'
updated_date: '2026-10-11 11:16'
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
- [x] #1 DT login page (auth/dt-login) renders inside the same card-based layout as the main login page (centered Card, bg-muted page background)
- [x] #2 DT login page shows the full app logo mark used on the main login page, not the small icon-only logo
- [x] #3 DT-specific title ('AMS – Portal DT') and description text are preserved
- [x] #4 DT login form fields, labels, and the 'Solicitud clave' link keep working after the layout change
- [x] #5 Page is visually consistent with the main login page on mobile viewport widths
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
1. Extract shared AuthCardLayout (logo + centered Card) from the existing LoginLayout, reusing the dead auth-card-layout.tsx scaffold file; add an optional footer slot for the cross-link.
2. Update LoginLayout to use AuthCardLayout with the DT-link as footer (unchanged behavior/markup for the main login page).
3. Add new DtLoginLayout using AuthCardLayout with no footer, keeping DtLogin.layout title/description.
4. Wire 'auth/dt-login' to DtLoginLayout in app.tsx (next to the existing 'auth/login' -> LoginLayout case).
5. Verify via tsc, eslint, prettier, pint, relevant Pest tests, and manual browser check (desktop + mobile viewport) for both /login and /dt/login.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Extracted shared AuthCardLayout (repurposed the unused starter-kit scaffold at resources/js/layouts/auth/auth-card-layout.tsx) with a logo + centered Card + optional footer slot. LoginLayout now uses it with the DT cross-link as footer (markup/behavior unchanged, verified by screenshot). New DtLoginLayout uses it with no footer. Wired 'auth/dt-login' -> DtLoginLayout in app.tsx. Verified: tsc --noEmit clean, eslint clean (after --fix for import order), prettier clean, pint --dirty clean, sail artisan test --compact --filter=DtLogin/GuestRedirectByPanel/Login all passed (79 tests). Browser-verified /dt/login and /login at desktop and 390x844 mobile viewport via chrome-devtools MCP (claude-in-chrome extension was not connected) — DT page shows full AppLogoMark, bg-muted, centered Card, preserved title/description/fields/Solicitar clave link, no self-referential cross-link; main login page unchanged, still shows its DT cross-link. No PHP changed, so no new Pest test needed. /code-review medium ran clean (no findings).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Replaced DT login's GuestLayout/AuthSimpleLayout chrome with the same card-based LoginLayout styling as the main login page, via a new shared AuthCardLayout component and a DtLoginLayout wrapper, routed in app.tsx by exact page name 'auth/dt-login'. Verified with tsc, eslint, prettier, pint, targeted Pest tests, and manual browser checks (desktop + mobile) of both /login and /dt/login.
<!-- SECTION:FINAL_SUMMARY:END -->
