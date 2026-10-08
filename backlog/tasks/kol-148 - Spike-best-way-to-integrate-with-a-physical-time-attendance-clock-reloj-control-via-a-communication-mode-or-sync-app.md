---
id: KOL-148
title: >-
  Spike: best way to integrate with a physical time-attendance clock (reloj
  control) via a communication mode or sync app
status: To Do
assignee: []
created_date: '2026-10-06 12:02'
updated_date: '2026-10-06 12:02'
labels:
  - spike
  - attendance
dependencies: []
type: spike
ordinal: 174000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Investigate the best way for Kolvi to communicate with a physical time-attendance clock (reloj control) device already installed in an office — as distinct from KOL-147, which is about building Kolvi's own kiosk/totem app with a fingerprint reader. This spike is about the other direction: many Chilean employers already own a third-party punch clock (card-based, PIN-based, or biometric) and need their marks to reach Kolvi, or need Kolvi to recognize that device as the registration method instead of replacing it.

Context: the Ley 40 Horas / Resolución 38 attendance-registration requirement (see KOL-145.5) accepts three methods — libro de asistencia, reloj control, or sistema electrónico de registro. Kolvi's own web/mobile marking covers the "sistema electrónico" option; this spike covers the "reloj control" option for employers who already have (or want) dedicated hardware, asking how Kolvi would receive and reconcile those punches.

This is research only. Do not build anything from this task — its output is a written finding and a recommendation, not code.

What this spike should settle:
- What communication modes exist for common attendance-clock hardware (local network push/pull protocols e.g. ZKTeco/Suprema-style TCP push, a vendor SDK, scheduled file export/import such as CSV/DAT pulled off the device, or a small local sync agent/app that polls the device and forwards punches to Kolvi's API).
- Whether a thin local "bridge" app (running on an office PC, polling or receiving pushes from the clock and calling Kolvi's API) is simpler and more vendor-agnostic than chasing per-vendor protocol integrations directly from the Laravel backend.
- How a clock-sourced punch should map onto Kolvi's existing Mark model and MarkManager pipeline: what identifies the employee (an employee code/card number registered against the clock vs. Kolvi's own user ID), what happens to geofence verification (a stationary office clock has fixed, known coordinates, unlike a phone), and how the compliant receipt (comprobante con folio, Resolución 38 Art. 13) still gets generated for a punch that did not originate from Kolvi's own UI.
- A brief check of what Talana, Buk and GeoVictoria offer here (hardware partnerships, supported clock brands, import tooling) — a light market check, not the deep per-competitor research KOL-146 is already doing for rotating shifts.
- A recommendation: build a generic import/sync mechanism now, partner with specific hardware vendors, or defer — with a rough scope estimate for whichever a first version would be.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Communication modes for common attendance-clock hardware (network push/pull, vendor SDK, file export/import, local sync agent) are listed with their trade-offs
- [ ] #2 Whether a local bridge app or a direct backend integration per vendor protocol is the better approach is worked out and reasoned
- [ ] #3 How a clock-sourced punch maps onto Kolvi's Mark model and MarkManager pipeline is documented, including employee identification, geofence handling for a stationary device, and how the Resolución 38 Art. 13 compliant receipt still gets generated
- [ ] #4 A brief check of what Talana, Buk and GeoVictoria offer for physical clock integration is recorded
- [ ] #5 A recommendation (approach plus a rough v1 scope) is written down; if viable, candidate follow-up tasks are named but not created or implemented as part of this spike
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
