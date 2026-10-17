---
id: KOL-155
title: 'Totem backend: Premise photo-capture setting and photo-on-mark storage'
status: To Do
assignee: []
created_date: '2026-10-17 09:19'
labels:
  - ready-for-agent
dependencies:
  - KOL-154
ordinal: 181000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

Admins have no way to verify that the person who punched at a Totem is actually the employee identified by RUT+password, beyond trusting the credential check itself. There's also no backend concept of a per-Premise policy for whether a photo should be captured at all, and no storage for a mark photo if one is.

Split from KOL-152. Depends on KOL-154 (RUT+password punch endpoint) — the photo attaches to a mark created by that endpoint.

## Solution

A new `Premise`-level boolean setting (`photo_capture_enabled`) controls whether a Totem should capture a photo on mark. The Totem app fetches this setting via a lightweight "premise config" read endpoint on provisioning and periodically thereafter, so it always reflects the latest admin configuration rather than a stale local toggle. When enabled, the punch request (KOL-154) additionally carries a photo (multipart), which is stored as a new low-resolution attachment on the `Mark` via the existing Spatie media-library convention already used by `Document`. The photo is resized/recompressed server-side on upload (target ≈480px wide JPEG, moderate compression) regardless of what the device sent, so the low-resolution policy isn't enforced client-side only. Photos are visible only to admins within the organization and retained only as long as the mark itself.

## User Stories

1. As an org admin, I want to toggle "capture photo on mark" per Premise from the web app, so that I can enable/disable the policy centrally without touching individual devices.
2. As the system, I want the Totem device to fetch the current photo-capture setting for its bound Premise, so that it always reflects the latest admin configuration rather than a stale local toggle.
3. As an employee, when photo capture is enabled, I want my photo attached to the specific mark I just made, so that an admin can later verify who actually made that mark.
4. As an org admin, I want a captured mark photo stored at low resolution, so that storage and bandwidth costs stay negligible even with many employees marking in/out daily.
5. As an org admin, I want mark photos visible only to admins within the organization (not exposed to employees or outside the API) and retained only as long as the mark itself, so that photo data doesn't outlive its verification purpose or leak access.

## Implementation Decisions

- New nullable relation/field on `Mark` (or a child model) storing the photo via the existing Spatie media-library convention already used by `Document`. Photo is resized/recompressed server-side on upload to enforce the low-resolution policy (target ≈480px wide JPEG, moderate compression) regardless of what the device sent, so the policy isn't enforced client-side only.
- New `Premise`-level boolean setting (`photo_capture_enabled`) exposed via an existing or new lightweight "premise config" read endpoint the Totem app fetches on provisioning and periodically thereafter.
- Punch endpoint from KOL-154 extended to accept an optional multipart `photo` field, only processed when the bound Premise has `photo_capture_enabled`.

## Testing Decisions

- Only test external behavior (HTTP status + response/media shape), never internal implementation details.
- Pest feature tests hitting the real API/web routes end-to-end, same seam as this repo's existing Document media-library tests.
- Cover: photo-enabled Premise stores a resized photo on the Mark; photo-disabled Premise ignores/rejects a photo field; premise-config endpoint reflects the current `photo_capture_enabled` value and updates after an admin toggles it; mark photo is not accessible via any employee-facing or unauthenticated route.

## Out of Scope

- TotemDevice provisioning and admin device list/revoke screen — see KOL-153.
- RUT+password punch identification and mark creation — see KOL-154.
- Biometric identification (fingerprint/face).
- Any new admin UI beyond the per-Premise toggle — no photo gallery/review UI beyond what's needed to confirm a mark's identity.

## Further Notes

- Corresponding frontend spec for the Totem RN app itself is published separately in the `totem` repo's own tracker (prefix `TOT`).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Admin can toggle photo_capture_enabled per Premise from the web app
- [ ] #2 Totem app can fetch the current photo-capture setting for its bound Premise via a config endpoint, reflecting the latest admin toggle
- [ ] #3 When photo capture is enabled, a photo submitted with a punch is stored as a low-resolution (~480px wide JPEG) attachment on the Mark
- [ ] #4 When photo capture is disabled, a photo field submitted with a punch is ignored or rejected (not stored)
- [ ] #5 Mark photos are retrievable only by admins within the organization, not by employees or unauthenticated requests
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
