---
id: KOL-151
title: DT forced password-change does not verify the current password
status: To Do
assignee: []
created_date: '2026-10-11 09:50'
labels: []
dependencies: []
priority: high
ordinal: 177000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
PasswordChangeController::store (app/Http/Controllers/Dt/PasswordChangeController.php) sets a new password for Auth::guard('dt')->user() from only 'password' + 'password_confirmation' — it never checks the user's current password. The route (POST /dt/password/change, named dt.password.change.update) sits inside the auth:dt middleware group but intentionally outside the password_expires gate, so it is reachable by ANY authenticated dt session at any time, not only one with an expired password. Reported by the user: after logging into the DT panel with an expired-but-correct password, they were dropped on this screen (intended, via PasswordExpires middleware) and were able to set a brand-new password with no re-verification of the old one. Anyone who gains temporary control of an already-authenticated DT session (shared/unlocked device, stolen session cookie, XSS) can permanently take over the account this way, since the owner's real password is never required. This is inconsistent with the rest of the app: the equivalent employee/admin flow (app/Http/Requests/Settings/PasswordUpdateRequest.php) already requires current_password via PasswordValidationRules::currentPasswordRules(). Add the same current-password check to the DT flow, reusing that trait/rule if practical.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 POST /dt/password/change (PasswordChangeController::store) rejects the request with a validation error when current_password does not match the authenticated dt user's stored password
- [ ] #2 The new password cannot be set unless the correct current password was supplied
- [ ] #3 resources/js/pages/auth/dt-password-change.tsx adds a 'current password' field and surfaces its validation error
- [ ] #4 A Pest feature test covers: wrong current_password is rejected and leaves the password unchanged; correct current_password + valid new password succeeds and updates password_changed_at
- [ ] #5 Existing forced-password-change-on-expiry flow (PasswordExpires middleware) still works end to end with the new field
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
