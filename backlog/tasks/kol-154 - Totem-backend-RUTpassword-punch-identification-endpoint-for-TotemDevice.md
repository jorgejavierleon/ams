---
id: KOL-154
title: 'Totem backend: RUT+password punch identification endpoint for TotemDevice'
status: Done
assignee: []
created_date: '2026-10-17 09:18'
updated_date: '2026-10-17 20:58'
labels:
  - ready-for-agent
dependencies:
  - KOL-153
ordinal: 180000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

Totem devices (provisioned in KOL-153) have a device-level identity but no way for an employee standing in front of one to identify themselves and record a mark. The only existing identification path is per-user email+password personal tokens, which a shared kiosk device cannot use — a single employee's personal token is never embedded in shared hardware.

Split from KOL-152. Depends on KOL-153 (TotemDevice model, `kiosk:punch` Sanctum ability, provisioning). Photo-on-mark capture is split out to the follow-on task KOL-155, which depends on this one.

## Solution

A new endpoint (or extended `/api/v1/marks`), guarded by the `kiosk:punch` Sanctum ability instead of a personal token, accepts `{rut, password, type, lat?, lng?, accuracy_m?, geo_status?}`. It resolves the `User` by RUT scoped to the device's bound `Premise`, verifies the password hash, and rejects on mismatch or cross-premise RUT. On success it calls the existing `MarkManager::createMark($type, $resolvedUser, ...)` unchanged — no new mark-creation logic, only a new way to arrive at the `$user` argument. The request also supports the existing offline-queue shape (`device_datetime` + `idempotency_key`, `queued_punch_too_old`/`queued_punch_in_future` rejection codes) already used by the mobile app, so the Totem app can reuse the same retry/sync semantics.

## User Stories

1. As an employee, I want to identify myself at a Totem using my RUT and password, so that I don't need a personal access token or my phone to clock in/out.
2. As the system, I want to verify the employee's RUT + password against their existing `User` password hash before creating a mark, so that no new credential store or auth mechanism is introduced for employees.
3. As the system, I want to reject a mark request if the RUT belongs to a `User` at a different Premise than the one the device is bound to, so that attendance stays tied to physical presence at the correct location.
4. As the system, I want to create the Mark using `MarkManager::createMark($type, $identifiedUser, ...)` exactly as existing authenticated flows do, so that folio/checksum/legal-snapshot stamping (`MarkObserver`) and geofence evaluation behave identically regardless of channel.
5. As the system, I want the existing one-`in`-plus-one-`out`-per-day duplicate rule (409) to apply identically to Totem-originated marks, so that attendance integrity rules aren't bypassed by a new entry channel.
6. As the system, I want geofence evaluation to run for Totem-originated marks using the Premise's fixed coordinates, so that existing anomaly-review tooling stays consistent regardless of entry channel.
7. As the system, I want the Totem's per-punch identification call to support the existing offline-queue shape (device timestamp + idempotency key, `queued_punch_too_old`/`queued_punch_in_future` rejection codes) already used by the mobile app, so that the Totem app can reuse the same retry/sync semantics.
8. As the system, I want wrong RUT/password combinations rejected with a clear error and no additional lockout or throttling beyond whatever Fortify already applies platform-wide, per explicit decision not to add device-specific lockout.

## Implementation Decisions

- New endpoint (or extended existing `/api/v1/marks`) guarded by the `kiosk:punch` ability instead of a personal token: accepts `{rut, password, type, lat?, lng?, accuracy_m?, geo_status?}`. Resolves `User` by RUT scoped to the device's bound `Premise`; verifies password hash; 401/422 on mismatch or cross-premise RUT. On success calls `MarkManager::createMark($type, $resolvedUser, ...)` unchanged.
- Request shape also accepts `device_datetime` + `idempotency_key`, reusing the exact `queued_punch_too_old` / `queued_punch_in_future` validation codes already implemented for the mobile app's queued-punch sync.
- No new lockout/throttle layer added specifically for RUT/password attempts; relies on whatever Fortify-level throttling already exists globally.
- No changes to `MarkManager`, `MarkObserver`, or `Geofence` — all reused unmodified via the explicit-user override already present.

## Testing Decisions

- Only test external behavior (HTTP status + response shape), never internal implementation details of `MarkManager`/`MarkObserver` — those are already covered by existing tests.
- Pest feature tests hitting the real API routes end-to-end, same seam as this repo's existing Mark-related tests.
- Cover: successful punch with valid RUT/password; punch rejected for wrong password; punch rejected for RUT belonging to a different Premise; duplicate in/out same day returns 409 identically to the existing mobile flow; revoked device token (from KOL-153) can no longer punch; queued/offline punch with `device_datetime` + `idempotency_key` behaves identically to the mobile app's existing queued-punch tests (reuse as prior art for the test shape).

## Out of Scope

- TotemDevice provisioning and admin device list/revoke screen — see KOL-153.
- Photo-on-mark capture and storage — see KOL-155.
- Biometric identification (fingerprint/face) — RUT+password is the chosen identification method and already satisfies Resolución 38 Art. 7g without needing a second non-biometric fallback, since it isn't a biometric method in the first place.
- Rate limiting/lockout specific to RUT/password attempts on Totem devices (explicit decision).
- Geofence enforcement changes — reused as-is; no new anomaly-review tooling.
- Support for an employee belonging to multiple Premises, or a Totem serving more than one Premise.

## Further Notes

- The cross-premise RUT rejection rule (story 3) is inferred from the existing `User.premise_id` field and the Geofence concept, not something explicitly confirmed during scoping — worth a quick sanity check against real multi-premise business rules before relying on it.
- Corresponding frontend spec for the Totem RN app itself is published separately in the `totem` repo's own tracker (prefix `TOT`).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Punch succeeds with valid RUT+password for a User at the device's bound Premise, creating a Mark via MarkManager::createMark
- [x] #2 Punch is rejected with a clear error for wrong password
- [x] #3 Punch is rejected when the RUT belongs to a User at a different Premise than the device
- [x] #4 Duplicate in/out same-day rule returns 409 identically to the existing authenticated mark flow
- [x] #5 A revoked TotemDevice token can no longer punch (401)
- [x] #6 Queued/offline punch with device_datetime + idempotency_key behaves identically to the mobile app's existing queued-punch handling, including queued_punch_too_old/queued_punch_in_future codes
- [x] #7 Geofence evaluation runs for Totem-originated marks using the Premise's fixed coordinates
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
1. Extract shared punch logic from MarkController (punchRules + recordPunch). 2. Add POST /api/v1/totem/marks (TotemMarkController) behind EnsureTotemDevice: identify by RUT+password scoped to device premise, then recordPunch. 3. Premise coords used for geofence when kiosk sends none. 4. Pest tests.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Endpoint: POST /api/v1/totem/marks. Auth::setUser(employee) during the punch so created_by on over-age additions names the employee (device is not a users row); restored in finally.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added POST /api/v1/totem/marks: RUT+password identification scoped to the device premise, reusing MarkController::recordPunch (409 rule, offline queue codes, geofence at premise coords).
<!-- SECTION:FINAL_SUMMARY:END -->
