---
id: KOL-8
title: 'Rate-limit the mobile API, starting with token issuance and password change'
status: Done
assignee: []
created_date: '2026-08-03 22:14'
updated_date: '2026-08-03 22:53'
labels: []
dependencies: []
documentation:
  - docs/prd-mobile-app.md
ordinal: 7000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Nothing under `/api` is rate limited. `route:list -v` shows the mobile routes carrying only the `api` group and `Authenticate:sanctum` — Laravel 11+ dropped `throttle:api` from the default `api` group, and `throttleApi()` was never registered in `bootstrap/app.php`. So every endpoint accepts unlimited requests.

Two of them are directly attackable:

- **`POST /api/v1/tokens` is public and unthrottled** — a credential-stuffing target with no cost to the attacker. This is gap **A1** in `docs/prd-mobile-app.md` §7.1.
- **`PUT /api/v1/user/password` lets `current_password` be brute-forced.** It needs a valid bearer token, so the attacker must already hold a stolen or sold phone — but Sanctum tokens never expire, and guessing the current password there escalates possession of a device into full account takeover (the endpoint also revokes the real employee's other devices on success).

The rest of the surface is a denial-of-service and scraping concern rather than an escalation one, but no route should be unlimited.

### The constraint that makes IP-only throttling wrong

Employees at a premise punch in over the same wifi or mobile NAT, so their requests share a source IP. An IP-keyed limiter on the token endpoint would let one employee's bad shift-start attempts lock out the whole premise at exactly the moment everyone needs to clock in — a worse outage than the attack it prevents. Key failed authentication on email + IP together, and keep the authenticated endpoints keyed per user.

### Success must clear the counter

`Illuminate\Routing\Middleware\ThrottleRequests` counts every request, not just failures. An employee who mistypes twice and then succeeds should start the next shift with a clean counter — see `Laravel\Fortify\LoginRateLimiter` for the shape the web login already uses.

### Related

The mobile client will need to handle `429` (respect `Retry-After`, show a Spanish "demasiados intentos" message rather than a generic failure). That is a kolvi-mobile task, not this one.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 POST /api/v1/tokens is throttled: repeated failed credential attempts for the same email from the same IP return 429 after the limit, while requests under the limit behave exactly as before
- [x] #2 The token endpoint throttle is keyed on email + IP together, so failures for one employee do not block a different employee attempting to sign in from the same premise IP
- [x] #3 A successful authentication clears that key's failure counter, so an employee who mistypes and then signs in correctly is not left near the limit
- [x] #4 PUT /api/v1/user/password is throttled per authenticated user, so wrong current_password attempts from a stolen device cannot be retried indefinitely; the limit returns 429
- [x] #5 Every remaining /api/v1 route carries a baseline rate limit — no route in routes/api.php is unlimited
- [x] #6 429 responses carry Retry-After and rate-limit headers so the app can back off instead of hammering
- [x] #7 Feature tests prove each limit: requests under it succeed, the request past it returns 429, the counter clears on success, and two different emails from one IP do not share a token-endpoint budget
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
1. Register a named 'api' rate limiter in AppServiceProvider (per-user when authenticated, per-IP for guests) and enable it for the api group with throttleApi() in bootstrap/app.php, so no /api/v1 route is unlimited.
2. Add App\Http\Middleware\ThrottleTokenIssuance (extends Illuminate ThrottleRequests) keyed on lowercased email + IP, 5/min, clearing the counter when the response is successful; attach it to POST /api/v1/tokens.
3. Attach throttle:6,1 to PUT /api/v1/user/password (runs after auth:sanctum, so the signature is the authenticated user id) matching routes/settings.php.
4. Pest feature tests in tests/Feature/Api/RateLimitApiTest.php: under-limit passes, past-limit 429 with Retry-After + X-RateLimit-* headers, success clears the token counter, two emails from one IP do not share a budget, password endpoint per-user, baseline covers the remaining routes.
5. pint --dirty, sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Baseline: throttleApi() in bootstrap/app.php points the api group at a named 'api' limiter in AppServiceProvider — 60/min per employee when authenticated, 100/min per IP for guests. The limiter resolves $request->user('sanctum') itself because the group throttle runs before auth:sanctum, otherwise every bearer request would land in the shared premise-IP bucket.

Token issuance: App\Http\Middleware\ThrottleTokenIssuance (extends ThrottleRequests) — 5/min keyed on lowercased email + IP, clearing the counter on a successful (2xx) response and restating the rate-limit headers so the app is not told it has fewer attempts than it does.

Password change: throttle:6,1 matching routes/settings.php. ThrottleRequests sorts after Authenticate in the framework's middleware priority list, so its signature is the employee id rather than the premise IP — proven by a test where one employee exhausting the limit leaves a colleague unaffected.

Tests: tests/Feature/Api/RateLimitApiTest.php (9 tests). Full suite 643 passing.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Rate limited the whole mobile API: throttleApi() points the api group at a named 'api' limiter (60/min per employee, 100/min per guest IP, resolving the sanctum guard itself because the group throttle runs before auth:sanctum), ThrottleTokenIssuance caps POST /api/v1/tokens at 5/min keyed on email + IP and clears the counter on a successful sign-in, and PUT /api/v1/user/password carries throttle:6,1 keyed per authenticated employee. Verified by tests/Feature/Api/RateLimitApiTest.php (9 tests: under-limit unchanged, 429 past the limit with Retry-After and X-RateLimit-* headers, counter cleared on success, separate budgets for two emails on one premise IP, per-employee password and baseline limits, and a route-table assertion that no /api/v1 route is unthrottled); full suite 643 passing, pint clean. No TypeScript touched.
<!-- SECTION:FINAL_SUMMARY:END -->
