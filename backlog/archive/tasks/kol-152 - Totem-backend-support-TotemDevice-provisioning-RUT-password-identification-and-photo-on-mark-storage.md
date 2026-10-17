---
id: KOL-152
title: >-
  Totem backend support: TotemDevice provisioning, RUT/password identification,
  and photo-on-mark storage
status: To Do
assignee: []
created_date: '2026-10-16 18:40'
updated_date: '2026-10-17 09:20'
labels:
  - ready-for-agent
dependencies: []
ordinal: 178000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

Organizations want to deploy unattended tablets/phones ("Totems") at their premises so employees can clock in/out (mark attendance) without using their personal phone or the admin-facing web app. Today the API only supports per-user authentication via email+password (`POST /api/v1/tokens`), with no way to identify a device as a trusted, premise-bound terminal, no way for an employee to authenticate with their RUT instead of email, and no way to attach a photo to an attendance mark. Without these, the Totem app has no backend to talk to.

## Solution

Add a new device-level principal (`TotemDevice`) distinct from a `User`, scoped to one `Premise`, with a Sanctum token carrying a narrow punch-only ability. An admin provisions a `TotemDevice` by authenticating with their own existing admin credentials and selecting a `Premise`; the resulting device token is handed to the Totem app for all subsequent requests. Each attendance mark from a Totem authenticates the *device* via that token and identifies the *employee* per-request via RUT + password (verified against the existing `User` password hash), then creates the `Mark` via the existing `MarkManager::createMark()` explicit-user override — no new mark-creation logic, only a new way to arrive at the `$user` argument. Optionally, if the `Premise` has photo capture enabled, the same request carries a photo which is stored as a new low-resolution attachment on the `Mark`. Admins can view and revoke `TotemDevice` tokens from a new minimal web screen.

## User Stories

1. As an org admin, I want to provision a new Totem device by logging in with my own admin credentials and selecting the Premise it belongs to, so that the device is bound to the correct location without needing a separate admin web screen for setup.
2. As an org admin, I want each Totem device's identity to be a device-level token rather than any employee's personal token, so that no single employee's credentials are embedded in shared hardware.
3. As an org admin, I want to see a list of all TotemDevices provisioned for my organization, so that I can audit which devices exist and where they're deployed.
4. As an org admin, I want to revoke a TotemDevice's token from the web app, so that a lost, stolen, or decommissioned tablet immediately stops being able to record attendance.
5. As an employee, I want to identify myself at a Totem using my RUT and password, so that I don't need a personal access token or my phone to clock in/out.
6. As the system, I want to verify the employee's RUT + password against their existing `User` password hash before creating a mark, so that no new credential store or auth mechanism is introduced for employees.
7. As the system, I want to reject a mark request if the RUT belongs to a `User` at a different Premise than the one the device is bound to, so that attendance stays tied to physical presence at the correct location.
8. As the system, I want to create the Mark using `MarkManager::createMark($type, $identifiedUser, ...)` exactly as existing authenticated flows do, so that folio/checksum/legal-snapshot stamping (`MarkObserver`) and geofence evaluation behave identically regardless of channel.
9. As the system, I want the existing one-`in`-plus-one-`out`-per-day duplicate rule (409) to apply identically to Totem-originated marks, so that attendance integrity rules aren't bypassed by a new entry channel.
10. As an org admin, I want to toggle "capture photo on mark" per Premise from the web app, so that I can enable/disable the policy centrally without touching individual devices.
11. As the system, I want the Totem device to fetch the current photo-capture setting for its bound Premise, so that it always reflects the latest admin configuration rather than a stale local toggle.
12. As an employee, when photo capture is enabled, I want my photo attached to the specific mark I just made, so that an admin can later verify who actually made that mark.
13. As an org admin, I want a captured mark photo stored at low resolution, so that storage and bandwidth costs stay negligible even with many employees marking in/out daily.
14. As an org admin, I want mark photos visible only to admins within the organization (not exposed to employees or outside the API) and retained only as long as the mark itself, so that photo data doesn't outlive its verification purpose or leak access.
15. As the system, I want geofence evaluation to run for Totem-originated marks using the Premise's fixed coordinates, so that existing anomaly-review tooling stays consistent regardless of entry channel.
16. As the system, I want the Totem's per-punch identification call to support the existing offline-queue shape (device timestamp + idempotency key, `queued_punch_too_old`/`queued_punch_in_future` rejection codes) already used by the mobile app, so that the Totem app can reuse the same retry/sync semantics.
17. As the system, I want wrong RUT/password combinations rejected with a clear error and no additional lockout or throttling beyond whatever Fortify already applies platform-wide, per explicit decision not to add device-specific lockout.
18. As an org admin, I want to provision more than one Totem device per Organization (one per Premise, potentially multiple per Premise), so that multi-site/multi-entrance organizations are supported.
19. As a developer, I want the `TotemDevice` Sanctum token to carry a narrow ability (e.g. `kiosk:punch`) rather than full user-equivalent access, so that a compromised device token can't be used to access unrelated API surface.
20. As a developer, I want the `KioskDevice` terminology from the earlier spike (KOL-147) renamed to `TotemDevice` throughout the implementation, so that domain vocabulary stays consistent with the product name "Totem" chosen for this feature.

## Implementation Decisions

- New `TotemDevice` model (Eloquent), superseding the earlier spike's proposed `KioskDevice` naming: belongs to one `Premise` (and transitively `Company`/`Organization`); has its own Sanctum personal access token with a narrow custom ability `kiosk:punch`.
- New endpoint: device provisioning. Admin authenticates with existing admin credentials, posts a selected `premise_id`; backend creates a `TotemDevice` row and issues a Sanctum token scoped to the `kiosk:punch` ability; returns the token to the caller (the Totem app, which persists it securely on-device).
- New minimal admin web screen/route: list `TotemDevice`s per Organization (label, Premise, created_at, last-used if trackable) with a revoke action, using the same Sanctum token-revocation mechanism already used by `DELETE /api/v1/tokens/current`, targeted at the device's token by id.
- New endpoint (or extended existing `/api/v1/marks`) guarded by the `kiosk:punch` ability instead of a personal token: accepts `{rut, password, type, lat?, lng?, accuracy_m?, geo_status?, photo?}` (multipart when photo present). Resolves `User` by RUT scoped to the device's bound `Premise`; verifies password hash; 401/422 on mismatch or cross-premise RUT. On success calls `MarkManager::createMark($type, $resolvedUser, ...)` unchanged.
- New nullable relation/field on `Mark` (or a child model) storing the photo via the existing Spatie media-library convention already used by `Document`. Photo is resized/recompressed server-side on upload to enforce the low-resolution policy (target ≈480px wide JPEG, moderate compression) regardless of what the device sent, so the policy isn't enforced client-side only.
- New `Premise`-level boolean setting (e.g. `photo_capture_enabled`) exposed via an existing or new lightweight "premise config" read endpoint the Totem app fetches on provisioning and periodically thereafter.
- Request shape also accepts `device_datetime` + `idempotency_key`, reusing the exact `queued_punch_too_old` / `queued_punch_in_future` validation codes already implemented for the mobile app's queued-punch sync.
- No new lockout/throttle layer added specifically for RUT/password attempts; relies on whatever Fortify-level throttling already exists globally.
- No changes to `MarkManager`, `MarkObserver`, or `Geofence` — all reused unmodified via the explicit-user override already present.

## Testing Decisions

- Only test external behavior (HTTP status + response shape), never internal implementation details of `MarkManager`/`MarkObserver` — those are already covered by existing tests.
- Pest feature tests hitting the real API routes end-to-end (same seam as this repo's existing Mark-related tests and Definition of Done: `sa test --compact`, Pint clean, a Pest test per PHP change).
- Cover: successful device provisioning; provisioning rejected for a non-admin; successful punch with valid RUT/password; punch rejected for wrong password; punch rejected for RUT belonging to a different Premise; duplicate in/out same day returns 409 identically to the existing mobile flow; photo-enabled Premise stores a resized photo on the Mark; photo-disabled Premise ignores/rejects a photo field; revoked device token can no longer punch; queued/offline punch with `device_datetime` + `idempotency_key` behaves identically to the mobile app's existing queued-punch tests (reuse as prior art for the test shape).

## Out of Scope

- Biometric identification (fingerprint/face) — RUT+password is the chosen identification method and already satisfies Resolución 38 Art. 7g without needing a second non-biometric fallback, since it isn't a biometric method in the first place.
- Any new admin UI beyond the minimal device list/revoke screen — no bulk device management, no device health/online-status dashboard.
- Rate limiting/lockout specific to RUT/password attempts on Totem devices (explicit decision).
- Geofence enforcement changes — reused as-is; no new anomaly-review tooling.
- Support for an employee belonging to multiple Premises, or a Totem serving more than one Premise.

## Further Notes

- The cross-premise RUT rejection rule (story 7) is inferred from the existing `User.premise_id` field and the Geofence concept, not something explicitly confirmed during scoping — worth a quick sanity check against real multi-premise business rules before relying on it.
- This spec supersedes the `KioskDevice` naming proposed in KOL-147; any references there should be read as superseded by this spec's `TotemDevice` vocabulary.
- Corresponding frontend spec for the Totem RN app itself is published separately in the `totem` repo's own tracker (prefix `TOT`).
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: jorgejavierleon@gmail.com
created: 2026-10-17 09:20
---
Superseded: split into KOL-153 (TotemDevice provisioning + admin device list/revoke), KOL-154 (RUT+password punch identification), and KOL-155 (Premise photo-capture setting + photo-on-mark storage). Archiving this task; see those for implementation.
---
<!-- COMMENTS:END -->
