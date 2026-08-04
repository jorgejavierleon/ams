---
id: KOL-1.1
title: Translate the password reset page an employee reaches from the mobile app
status: To Do
assignee: []
created_date: '2026-08-04 19:36'
labels:
  - module-auth
  - frontend
dependencies: []
documentation:
  - docs/prd-mobile-app.md
parent_task_id: KOL-1
type: feature
ordinal: 29000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
KOL-9 gave the employee mobile app a forgot-password link. The mail it sends links to this console's existing reset page — `GET /reset-password/{token}`, rendered by `resources/js/pages/auth/reset-password.tsx` — and the phone's browser opens it. That page is in English: 'Reset password', 'Please enter your new password below', 'Email', 'Password', 'Confirm password', and the submit button.

Until that link shipped, this page was reachable only by console administrators, and its English was part of the console's English like every other page. It now sits in the middle of an employee flow. The app tells the employee, in Spanish, to go and read their mail; the mail is in Spanish; the page the mail links to is not. Res. 38 Art. 5 requires Spanish for what a worker is asked to read, and this is the one screen in that flow that does not comply.

The infrastructure is already there — this is not blocked on the rest of KOL-1. `HandleInertiaRequests::share()` already exposes `translations` keyed by namespace, `resources/js/hooks/use-translations.ts` provides the `t()` helper, and `lang/es/ui.php` is the catalogue (with `lang/en/ui.php` alongside it). Components such as `resources/js/components/position-form-dialog.tsx` show the established pattern. What is missing is an `auth` namespace: `lang/es/ui.php` has none, and all nine pages under `resources/js/pages/auth/` use zero translations.

Two things deliberately left out of scope:

- **The login page the reset redirects to.** On success Fortify redirects to `/login` with the Spanish status 'Su contraseña ha sido restablecida.' rendered on an English page ('Log in to your account', 'Email address', 'Remember me'). The employee therefore still meets English one click later. It is the console's front door for administrators too, so translating it is a bigger decision than this page; it belongs to the parent KOL-1.
- **The other seven auth pages** — `confirm-password`, `verify-email`, `forgot-password`, and the `dt-*` and `saas-*` variants, which serve DT inspectors and SaaS administrators rather than employees. Also KOL-1.

If the `auth` namespace added here is shaped so the sibling pages can use it later, the rest of KOL-1's auth work becomes catalogue entries rather than refactoring.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 resources/js/pages/auth/reset-password.tsx renders every visible string through the existing t() helper, with no English literal left in the file
- [ ] #2 The strings live in a new auth namespace in lang/es/ui.php with the English equivalents in lang/en/ui.php, following the convention that file documents
- [ ] #3 The page's layout title and description are translated too, not only the form body
- [ ] #4 Validation errors shown under the password fields arrive in Spanish, from lang/es/validation.php rather than from new frontend copy
- [ ] #5 Opening a real reset link on a phone-sized viewport shows the page in Spanish end to end, and submitting it still resets the password
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
