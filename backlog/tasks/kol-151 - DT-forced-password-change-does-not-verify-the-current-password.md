---
id: KOL-151
title: DT forced password-change does not verify the current password
status: Done
assignee:
  - '@jorge'
created_date: '2026-10-11 09:50'
updated_date: '2026-10-11 11:33'
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
- [x] #1 POST /dt/password/change (PasswordChangeController::store) rejects the request with a validation error when current_password does not match the authenticated dt user's stored password
- [x] #2 The new password cannot be set unless the correct current password was supplied
- [x] #3 resources/js/pages/auth/dt-password-change.tsx adds a 'current password' field and surfaces its validation error
- [x] #4 A Pest feature test covers: wrong current_password is rejected and leaves the password unchanged; correct current_password + valid new password succeeds and updates password_changed_at
- [x] #5 Existing forced-password-change-on-expiry flow (PasswordExpires middleware) still works end to end with the new field
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add optional $guard param to PasswordValidationRules::currentPasswordRules() so it can emit 'current_password:{guard}' for non-default guards.
2. Update PasswordChangeController::store to use the trait (currentPasswordRules('dt') + passwordRules()) instead of ad-hoc validate rules; drop the now-unused Password import.
3. Add a 'Clave actual' (current_password) field to resources/js/pages/auth/dt-password-change.tsx with InputError + autoComplete=current-password, and include it in resetOnSuccess.
4. Update the existing 'dt user can change their expired password' test to send current_password; add tests for wrong current_password being rejected (password unchanged) and correct current_password succeeding + updating password_changed_at.
5. Run pint --dirty, the dt test group, and npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified via sail artisan test --compact --group=dt: 122/122 passed. pint --dirty clean. npm run types:check clean. Browser-verified end-to-end with chrome-devtools: expired dt user -> redirected to /dt/password/change -> wrong current_password shows 'La contraseña es incorrecta.' and leaves password unchanged -> correct current_password succeeds and proceeds past the password gate. /code-review medium ran clean (no findings).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added current_password verification to the DT forced password-change flow. PasswordValidationRules::currentPasswordRules() now accepts an optional guard so it emits 'current_password:dt' for the dt guard; PasswordChangeController::store uses the trait (currentPasswordRules('dt') + passwordRules()) instead of ad-hoc rules. Added a 'Clave actual' field to dt-password-change.tsx with its own InputError. Added/updated Pest tests for wrong vs correct current_password. Verified with sail artisan test (dt group, 122 passed), pint, tsc, a clean /code-review, and a manual browser run through the full expired-password -> reject -> succeed flow.
<!-- SECTION:FINAL_SUMMARY:END -->
