---
id: KOL-138
title: Redesign the login page UI and translate it to Spanish
status: Done
assignee: []
created_date: '2026-10-03 09:38'
updated_date: '2026-10-03 19:09'
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
- [x] #1 Login page displays the Kolvi brand logo instead of the generic starter-kit icon
- [x] #2 All visible text on the login page (field labels, placeholders, button, 'forgot password' link, remember-me, page title/description, status/flash messages) is in Spanish
- [x] #3 Visual layout/spacing/styling is refreshed and reviewed in the browser by the user before merge
- [x] #4 Change is scoped to the main employee login page; saas-login.tsx and dt-login.tsx are not touched unless the user asks
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add a dedicated LoginLayout (resources/js/layouts/auth/login-layout.tsx) used only for the auth/login page name in app.tsx, so saas-login/dt-login/other auth pages keep their current GuestLayout/AuthSimpleLayout untouched. 2. LoginLayout: muted page background + Card surface, real Kolvi AppLogo brand lockup instead of AppLogoIcon, centered title/description from Login.layout props. 3. Translate all visible strings in login.tsx to Spanish (labels, placeholders, button, forgot-password link, remember-me, Head title, layout title/description); backend flash/validation messages are already Spanish via lang/es/*. 4. No PHP changes needed - frontend only. Run pint (no PHP touched, skip), sa test --compact for Auth/LayoutTest, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Added dedicated LoginLayout (resources/js/layouts/auth/login-layout.tsx), wired only for the 'auth/login' Inertia page name in app.tsx, so saas-login/dt-login/forgot-password/etc. keep the original GuestLayout unchanged. Translated all visible login.tsx strings to Spanish (backend flash/validation messages were already Spanish via lang/es/*). Found and fixed a pre-existing contrast bug in app-logo.tsx: the 'kolvi' wordmark used var(--brand-navy-deep) for text color, which equals the dark-mode background almost exactly, making it unreadable in dark mode on any non-sidebar surface; changed to text-foreground. No PHP touched.

Verified in browser (desktop, dark mode, 390px mobile): Kolvi brand mark displays (AC1), all login text in Spanish (AC2), visual refresh reviewed and approved by user (AC3), /saas/login and /dt/login confirmed still rendering the original generic icon/layout (AC4). npm run types:check and prettier --check pass on touched files; sa test --compact --filter=AuthenticationTest passes (5 passed, 1 skipped). DoD #1 and #4 N/A - no PHP files changed.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Redesigned the employee login page: new Card-on-muted-background LoginLayout (resources/js/layouts/auth/login-layout.tsx) wired only for the auth/login page name so other auth pages (saas-login, dt-login, forgot-password, etc.) are unaffected; shows the real Kolvi brand mark (icon only, per user feedback) instead of the generic starter-kit icon. Translated all visible login.tsx text to Spanish. Fixed a pre-existing dark-mode contrast bug in app-logo.tsx's wordmark color as a side effect of extracting the icon mark into app-logo-mark.tsx. No PHP changes.
<!-- SECTION:FINAL_SUMMARY:END -->
