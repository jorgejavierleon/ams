---
id: KOL-153
title: >-
  Totem backend: TotemDevice provisioning, kiosk:punch ability, and admin device
  list/revoke
status: To Do
assignee: []
created_date: '2026-10-17 09:18'
updated_date: '2026-10-17 09:19'
labels:
  - ready-for-agent
dependencies: []
ordinal: 179000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

Organizations want to deploy unattended Totem tablets/phones at their premises for employee clock-in/out. Today there is no way to identify a device as a trusted, premise-bound terminal — only per-user email+password tokens exist (`POST /api/v1/tokens`). Without a device-level identity, the Totem app has no credential to authenticate with at all.

Split from KOL-152 (originally a single combined spec). This is the foundation layer: device identity, provisioning, and admin visibility/revocation. The RUT+password punch flow and photo-on-mark feature are split into follow-on tasks (KOL-154, KOL-155) that depend on this one.

## Solution

Add a new device-level principal `TotemDevice`, distinct from `User`, scoped to one `Premise`, with its own Sanctum personal access token carrying a narrow custom ability `kiosk:punch` (not full user-equivalent access). An admin provisions a `TotemDevice` by authenticating with their own existing admin credentials and selecting a `Premise`; the resulting device token is handed to the Totem app for all subsequent requests. Admins can view and revoke `TotemDevice` tokens from a new minimal web screen.

## User Stories

1. As an org admin, I want to provision a new Totem device by logging in with my own admin credentials and selecting the Premise it belongs to, so that the device is bound to the correct location without needing a separate admin web screen for setup.
2. As an org admin, I want each Totem device's identity to be a device-level token rather than any employee's personal token, so that no single employee's credentials are embedded in shared hardware.
3. As an org admin, I want to see a list of all TotemDevices provisioned for my organization, so that I can audit which devices exist and where they're deployed.
4. As an org admin, I want to revoke a TotemDevice's token from the web app, so that a lost, stolen, or decommissioned tablet immediately stops being able to record attendance.
5. As an org admin, I want to provision more than one Totem device per Organization (one per Premise, potentially multiple per Premise), so that multi-site/multi-entrance organizations are supported.
6. As a developer, I want the `TotemDevice` Sanctum token to carry a narrow ability (e.g. `kiosk:punch`) rather than full user-equivalent access, so that a compromised device token can't be used to access unrelated API surface.
7. As a developer, I want the `KioskDevice` terminology from the earlier spike (KOL-147) renamed to `TotemDevice` throughout the implementation, so that domain vocabulary stays consistent with the product name "Totem" chosen for this feature.

## Implementation Decisions

- New `TotemDevice` Eloquent model, superseding the earlier spike's proposed `KioskDevice` naming: belongs to one `Premise` (and transitively `Company`/`Organization`); has its own Sanctum personal access token with a narrow custom ability `kiosk:punch`.
- New endpoint: device provisioning. Admin authenticates with existing admin credentials, posts a selected `premise_id`; backend creates a `TotemDevice` row and issues a Sanctum token scoped to the `kiosk:punch` ability; returns the token to the caller (the Totem app, which persists it securely on-device).
- New minimal admin web screen/route: list `TotemDevice`s per Organization (label, Premise, created_at, last-used if trackable) with a revoke action, using the same Sanctum token-revocation mechanism already used by `DELETE /api/v1/tokens/current`, targeted at the device's token by id.

## Testing Decisions

- Only test external behavior (HTTP status + response shape), never internal implementation details.
- Pest feature tests hitting the real API/web routes end-to-end, following this repo's existing convention for Sanctum-token tests.
- Cover: successful device provisioning; provisioning rejected for a non-admin; device list scoped to the admin's own Organization; revoke action invalidates the token (subsequent authenticated request with that token returns 401); multiple devices per Premise supported.

## Out of Scope

- RUT+password employee identification and mark creation — see KOL-154.
- Photo-on-mark capture and storage — see KOL-155.
- Any new admin UI beyond the minimal device list/revoke screen — no bulk device management, no device health/online-status dashboard.
- Rate limiting/lockout specific to device provisioning beyond whatever Fortify already applies platform-wide.

## Further Notes

- This spec supersedes the `KioskDevice` naming proposed in KOL-147; any references there should be read as superseded by this spec's `TotemDevice` vocabulary.
- Corresponding frontend spec for the Totem RN app itself is published separately in the `totem` repo's own tracker (prefix `TOT`).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Admin can provision a TotemDevice for a Premise they administer and receives a Sanctum token scoped to kiosk:punch
- [ ] #2 Provisioning is rejected (403) for a non-admin user
- [ ] #3 A kiosk:punch-scoped token cannot access unrelated authenticated API surface reserved for full user tokens
- [ ] #4 Admin web screen lists all TotemDevices for their Organization with Premise, created_at, and last-used
- [ ] #5 Admin can revoke a TotemDevice token from the web screen; the token is immediately rejected on subsequent API use (401)
- [ ] #6 Multiple TotemDevices can be provisioned for the same Premise and for different Premises within an Organization
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
