---
id: KOL-6
title: Revoke the current device token on DELETE /api/v1/tokens/current
status: Done
assignee: []
created_date: '2026-08-03 11:12'
updated_date: '2026-08-03 16:14'
labels: []
dependencies: []
documentation:
  - docs/prd-mobile-app.md
ordinal: 5000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The employee mobile app (kolvi-mobile, task KMO-12 "Sign out with server-side token revocation") has a `Cerrar sesión` action that must revoke the device's Sanctum token server-side before it clears local state. Clearing local storage is not sign-out: the token stays valid on the server, so a lost or sold phone stays authorised indefinitely and the employee has no way to end that from the app.

No such endpoint exists today. `routes/api.php` mounts `POST /api/sanctum/token`, `GET /api/user` and three `marks` routes, and nothing revokes. `TokenController::issueToken` already deletes the device's previous token on re-login, so the mechanism is there — it just cannot be reached deliberately.

This is gap **A2** in `docs/prd-mobile-app.md` §7.1.

### Contract the mobile client expects

```
DELETE /api/v1/tokens/current
Authorization: Bearer <the token being revoked>

204 No Content
```

Behaviour: delete the personal access token the request authenticated with — `$request->user()->currentAccessToken()->delete()` — and nothing else. Only that one device's token, never the employee's other devices: an employee who signs out on their phone must stay signed in on a tablet.

### The path is versioned, and nothing under /api/v1 exists yet

The mobile app targets `/api/v1` exclusively (decision D7 in the app's `docs/design-decisions.md`), and its client is already built around an `API_VERSION_PREFIX` of `/api/v1`. This endpoint is the first route that prefix will actually need, so the work includes standing up the `v1` route group alongside the existing unversioned mark routes.

If there is a good reason to mount it unversioned instead, say so on this ticket before building — the mobile side is coded against `/api/v1/tokens/current` today and would need a one-line change plus a re-run of its flows.

### How the app behaves in the meantime

KMO-12 ships against this contract before it exists. Until then the DELETE returns 404, which the app treats as "revocation did not happen": it still clears the token locally and tells the employee, in Spanish, that the token stays active until the device reconnects. So this ticket is what makes that warning stop appearing — the app needs no further change once the endpoint answers 204.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 DELETE /api/v1/tokens/current, behind auth:sanctum, deletes the personal access token the request authenticated with and returns 204
- [x] #2 Only the calling device's token is deleted — the same employee's tokens on other devices keep working
- [x] #3 The request is rejected with 401 when it carries no token or a token that was already revoked
- [x] #4 A feature test covers revocation, the other-device isolation, and the unauthenticated case
- [x] #5 The whole mobile surface is mounted under /api/v1 (tokens, user, marks) with v1.* route names, and no unversioned mobile route remains
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
1. Add TokenController::revokeCurrent — delete $request->user()->currentAccessToken() and return 204 No Content.
2. Mount a /api/v1 route group in routes/api.php alongside (not replacing) the existing unversioned routes, behind auth:sanctum, with DELETE tokens/current named v1.tokens.current.destroy.
3. Add tests/Feature/Api/TokenApiTest.php covering: real bearer token revoked -> 204 and token row deleted; other-device token of the same user still authenticates; no token -> 401; revoked token reused -> 401.
4. vendor/bin/pint --dirty and sa test --compact --filter for the api group.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as specced — mounted at /api/v1/tokens/current, no deviation from the mobile contract; the unversioned mark routes were left untouched.

Notes:
- Route group: Route::prefix('v1')->name('v1.')->middleware('auth:sanctum') in routes/api.php, below the existing unversioned group. Route name: v1.tokens.current.destroy.
- TokenController::revokeCurrent calls $user->currentAccessToken()->delete() and returns 204. No nullsafe/instanceof guard: Larastan types currentAccessToken() as non-nullable PersonalAccessToken, and the TransientToken (session-auth) path is unreachable because the api middleware group has no StartSession.
- Tests (tests/Feature/Api/TokenApiTest.php) use real bearer tokens via withToken(), not Sanctum::actingAs, since actingAs installs a mock token with nothing to delete.
- Multi-request tests must call $this->app['auth']->forgetGuards() between requests — the test app is not rebooted between calls, so the sanctum RequestGuard caches the user it resolved and a revoked token would appear to still authenticate (this produced a false 200 on first run).
- docs/architecture.md gained a 'Mobile API' section recording that /api/v1 is the prefix for new endpoints and the unversioned routes are legacy.

SCOPE CHANGE (user-approved, supersedes original AC #4): instead of standing v1 up alongside the unversioned routes, the entire mobile surface moved under /api/v1. Rationale: the only reason to leave a route unversioned is a deployed client you cannot update, and kolvi-mobile is pre-release — its client already defines API_VERSION_PREFIX='/api/v1' and special-cases the two unversioned paths in src/features/auth/auth-api.ts. The PRD also anticipated a 'v1 successor' to /api/marks (§7).

New surface (old paths removed outright, no aliases — nothing is deployed against them):
- POST   /api/v1/tokens          v1.tokens.store            (was POST /api/sanctum/token)
- DELETE /api/v1/tokens/current  v1.tokens.current.destroy  (new)
- GET    /api/v1/user            v1.user.show               (was GET /api/user)
- POST   /api/v1/marks           v1.marks.store             (was POST /api/marks)
- GET    /api/v1/marks           v1.marks.index
- GET    /api/v1/marks/{mark}    v1.marks.show

'sanctum/token' was renamed to 'tokens' so issue/revoke are symmetric and the auth package stops leaking into the public contract.

/api/leaves/calendar was deliberately NOT versioned: it lives in routes/web.php, is session-authenticated and is consumed by the React frontend that deploys in the same commit. The rule recorded in docs/architecture.md is 'version what a client cannot redeploy with you'.

FOLLOW-UP REQUIRED IN kolvi-mobile: src/features/auth/auth-api.ts hardcodes TOKEN_PATH='/api/sanctum/token' and USER_PATH='/api/user' and must switch to the versioned base URL; the mark endpoints move under the prefix too. The app is broken against this backend until that lands.

Verification: sail artisan test --compact --group=api => 25 passed / 106 assertions (TokenApiTest covers 204 + row deleted, other-device isolation, cross-employee isolation, revoked-token 401, missing/garbage-token 401). Full suite: 623 tests, 619 passed, 4 pre-existing skips. pint --dirty clean; phpstan clean on routes/api.php and app/Http/Controllers/Api; npm run types:check clean. route:list confirms the six v1.* routes and zero unversioned mobile routes.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added DELETE /api/v1/tokens/current (TokenController::revokeCurrent), which deletes only the request's own personal access token and returns 204, so mobile sign-out revokes server-side without touching the employee's other devices. Per user decision the scope widened from 'stand v1 up alongside' to moving the entire mobile surface under /api/v1 with v1.* names (POST /api/v1/tokens replacing POST /api/sanctum/token, plus user and marks), old paths removed since kolvi-mobile is pre-release; /api/leaves/calendar stays unversioned as a frontend-coupled endpoint. Verified by tests/Feature/Api/TokenApiTest.php (5 tests) plus the retargeted Mark/User API tests: 25 api-group tests pass, full suite 619 passed / 4 skipped, pint + phpstan + types:check clean.
<!-- SECTION:FINAL_SUMMARY:END -->
