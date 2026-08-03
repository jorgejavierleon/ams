---
id: KOL-7
title: Change the authenticated employee's password on PUT /api/v1/user/password
status: Done
assignee:
  - '@claude'
created_date: '2026-08-03 20:16'
updated_date: '2026-08-03 20:22'
labels: []
dependencies: []
documentation:
  - docs/prd-mobile-app.md
ordinal: 6000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The employee mobile app (kolvi-mobile, task KMO-13 "Change password") needs the worker to be able to change their own password from the phone. Resolución 38 Art. 7f requires exactly that, with an automatic confirmation email — it is a compliance checklist item (F05), not a convenience feature.

No such endpoint exists. `routes/api.php` mounts tokens, user and marks and nothing else, so a mobile-only employee — the person this app is built for — has no way to change their password without a desktop. This is gap **A3** in `docs/prd-mobile-app.md` §7.1.

### Contract the mobile client expects

```
PUT /api/v1/user/password
Authorization: Bearer <the device token>

{ "current_password": "...", "password": "...", "password_confirmation": "..." }

204 No Content
```

Refusals are Laravel's ordinary validation envelope, so the app can put each message under the input it belongs to:

- `422` with `errors.current_password` when the current password is wrong
- `422` with `errors.password` when the new one fails policy or the confirmation does not match

### Most of this already exists

- `App\\Concerns\\PasswordValidationRules` has `currentPasswordRules()` (the `current_password` rule) and `passwordRules()` (`Password::default()` plus `confirmed`). `Settings\\SecurityController::update` is the web equivalent to mirror, via `PasswordUpdateRequest`.
- `lang/es/validation.php` already answers in Spanish — `current_password` is 'La contraseña es incorrecta.' and the `password.*` block covers the policy failures. Art. 5 requires Spanish and no new copy is needed to get it.
- **The Art. 7f confirmation email is already wired.** `UserObserver::updated` mails `AuthProfileUpdated` to the previous `personal_email ?: email` whenever `password` changes. An endpoint that goes through `$user->update()` gets it for free — but a test has to prove it, because an implementation reaching for `forceFill()->saveQuietly()` would silently skip the observer and break the compliance requirement with no visible symptom.

### Token policy

Sanctum tokens are not tied to the password hash, so nothing is revoked by default. Decided with the mobile side: **revoke the employee's other device tokens and keep the one that made the change.** A password changed because someone else has the account should end their sessions, while the employee holding the phone stays signed in — bouncing them to the login screen at shift start would stop them punching, which is a worse failure than the one it prevents.

The mobile app handles either behaviour (KMO-13 AC#4): a revoked current token produces a 401 on the next request, which the app already turns into a clean re-login. But keeping it is the intended behaviour and the tests should pin it.

### Out of scope

Forgot-password (gap A4, mobile task KMO-14) is a separate flow with a separate ticket. This one is the authenticated change only.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 PUT /api/v1/user/password behind auth:sanctum changes the authenticated employee's password and returns 204
- [x] #2 A wrong current password is refused with 422 and a Spanish message under errors.current_password, and the stored password is unchanged
- [x] #3 A new password failing Password::default(), or one whose password_confirmation does not match, is refused with 422 and a Spanish message under errors.password
- [x] #4 The request is rejected with 401 when it carries no token or a revoked one
- [x] #5 A successful change sends the AuthProfileUpdated confirmation email, and a feature test asserts it was mailed rather than assuming the observer fired
- [x] #6 A successful change revokes the employee's other device tokens and leaves the token that made the request working
- [x] #7 Feature tests cover the success path, both 422 branches, the unauthenticated case, the email and the token policy
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Built as app/Http/Controllers/Api/PasswordController.php, mounted at PUT /api/v1/user/password (route name v1.user.password.update).

Three decisions worth recording:

1. **The guard is named on the current_password rule** — 'current_password:sanctum', not the bare rule from PasswordValidationRules::currentPasswordRules(). Unqualified, the rule resolves the *default* guard; auth:sanctum does call shouldUse('sanctum') so it happens to work today, but if that ever stops holding the rule compares against a null web-guard user and rejects every correct password with a message telling the employee they got their own password wrong. A test pins it.

2. **password_changed_at is deliberately not stamped.** hasActivePassword() treats null as 'no expiry', so writing it would start a 90-day expiry clock on employees who never had one — and the web console's own change (Settings\SecurityController::update) does not write it either. Stamping it is a separate decision about whether employee passwords should expire at all.

3. **The token guard was removed after PHPStan.** An instanceof PersonalAccessToken check around currentAccessToken() reads as dead code to Larastan, which resolves Sanctum's '@return TToken' generic. Nothing under routes/api.php can be session-authenticated — the api middleware group starts no session and statefulApi() is not registered — so the keyless TransientToken case is unreachable, and TokenController::revokeCurrent already makes the same assumption. The comment says what would need revisiting.

Validation: 11 new Pest tests in tests/Feature/Api/PasswordApiTest.php, all green. --group=api 36/36. Full suite 634 tests, 630 passed, 4 pre-existing skips. phpstan clean, pint clean on the touched files.

Pre-existing and untouched: 'composer lint:check' fails on 10 unrelated test files (single_blank_line_at_eof). The failure list is byte-identical on master, so it predates this branch and fixing it here would be unrelated churn.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added PUT /api/v1/user/password so a mobile-only employee can change their own password, closing gap A3 and Res. 38 Art. 7f (checklist F05). The endpoint validates the current password against the sanctum guard, applies Password::default() plus confirmation to the new one, and answers 422 with Spanish field messages from lang/es/validation.php that the app renders under the matching input.

The Art. 7f confirmation email needed no new code — UserObserver::updated already mails AuthProfileUpdated on a password change — but a test now asserts it is queued, because an implementation reaching for saveQuietly() would skip the observer and drop the compliance obligation with no visible symptom.

A successful change revokes the employee's other device tokens and keeps the one that made the request, so a password changed under duress ends the other sessions without locking the employee out of punching on the phone in their hand.
<!-- SECTION:FINAL_SUMMARY:END -->
