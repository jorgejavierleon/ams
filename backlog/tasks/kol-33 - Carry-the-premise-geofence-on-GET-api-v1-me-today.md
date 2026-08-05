---
id: KOL-33
title: Carry the premise geofence on GET /api/v1/me/today
status: Done
assignee: []
created_date: '2026-08-05 10:37'
updated_date: '2026-08-05 20:38'
labels: []
dependencies: []
documentation:
  - docs/prd-mobile-app.md
priority: high
type: feature
ordinal: 32000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The mobile app (kolvi-mobile, task KMO-16 "Geolocation permission and the three location states") draws a location card above the shift card that tells an employee, before they tap, whether they can punch. It has three states — confirmed, out of range, no GPS signal — and the first two need to know where the premise is and how far from it still counts.

Neither fact is on the wire. `Premise` has `lat` and `lng` but **no radius column at all**, and `TodayController` does not send the coordinates it does have. PRD §6 already asks for both ("a radius per premise, `Premise.geofence_radius_meters`, nullable = no geofence") and PRD §3.2 lists the geofence parameters among what `/me/today` returns; KOL-31 anticipated this block and left room for it rather than versioning the resource later.

Until it ships, the mobile client can only exercise the degraded path, because every premise is currently a premise with no geofence.

### Contract the mobile client expects

Nested inside `shift`, since it is the premise *that shift is worked at*:

```json
{
  "shift": {
    "premise": "Sucursal Ñuñoa",
    "start_time": "08:00:00",
    "end_time": "17:00:00",
    "lunch_start_time": "13:00:00",
    "lunch_end_time": "14:00:00",
    "geofence": { "lat": -33.4569, "lng": -70.5975, "radius_meters": 150 }
  }
}
```

The authoritative reading is `src/features/marcaje/today-api.ts` in kolvi-mobile — that parser is what a response is graded against.

Notes on the shape:

- `geofence` is `null` (or omitted) when the premise has no coordinates. `radius_meters` is `null` when no radius is configured. Both cases are legitimate and the app has a defined behaviour for them: it never shows the out-of-range state and never blocks a punch. That is a legal position, not a nicety — refusing to record a punch an employee actually made is worse than recording a suspect one (kolvi-mobile docs/design-decisions.md, D-F1-c).
- `lat`/`lng` are numbers, not strings. `Premise` casts them to float already; a `decimal:8` cast would emit them quoted and the client rejects that.
- The client's distance evaluation is **advisory only** and is used to render a card. The authoritative geofence decision is the server's, at punch time — that is a separate ticket (PRD §6 item 2: haversine on `POST /api/v1/marks`, persisting `inside|outside|unknown` alongside the reported accuracy), and it is what kolvi-mobile KMO-17/KMO-18 will need. Do not build it here.
- Unknown keys are ignored by the client, so adding this block breaks nothing already shipped.

### The admin side

A radius nobody can set is a radius that is always null, so the column needs a field on the premise form — `PremiseController` already validates `lat` and `lng` at lines 144 onward and is where it belongs. Nullable, in metres, with a sane floor: a radius of 5 m is a premise nobody can ever punch at.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A nullable geofence_radius_meters column exists on premises, in metres, and is fillable and cast on the model
- [x] #2 The premise form accepts and persists the radius, validated as nullable, numeric and at least 25 metres, and leaving it empty means no geofence
- [x] #3 GET /api/v1/me/today returns shift.geofence as {lat, lng, radius_meters} with lat and lng as JSON numbers, not strings
- [x] #4 shift.geofence is null when the premise has no coordinates, and radius_meters is null when no radius is configured — neither is an error
- [x] #5 The geofence block adds no query to the response — the premise is already loaded for the shift card's label
- [x] #6 A Pest feature test covers a premise with coordinates and a radius, one with coordinates and no radius, and one with no coordinates
- [x] #7 The demo seeder gives employee@example.com a premise with coordinates and a radius, so the mobile Maestro flow can drive in and out of range with bin/device geo
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
1. Migration: nullable unsigned geofence_radius_meters on premises (after lng).
2. Premise model: fillable + integer cast + @property docblock.
3. PremiseController: validate nullable|numeric|min:25, expose on edit() payload.
4. premise-form.tsx: radius input in the Location section; edit.tsx type/initial; es+en translations.
5. TodaySummary/TodayController: carry the already-loaded Premise (no extra query) alongside premiseLabel.
6. TodayResource: shift.geofence = {lat, lng, radius_meters}, null when the premise has no coordinates.
7. PremiseFactory: withGeofence() state; UserSeeder: Sucursal Centro gets fixed Santiago coords + 150 m radius.
8. Tests: TodayApiTest geofence cases (coords+radius, coords no radius, no coords, query count) and PremiseManagementTest radius persist/validation.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Geofence shipped as a value object (App\Support\Geofence) rather than raw fields on TodaySummary, so the 'null without coordinates' rule lives in one place. TodayController now reads $user->premise once and uses it for both the shift label and the geofence, which is what keeps the block query-free. Column is unsignedSmallInteger (caps at 65 km); validation floor is 25 m. Demo seeder: Sucursal Centro = -33.4489/-70.6693 with a 150 m radius; Sucursal Norte deliberately has none.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a nullable geofence_radius_meters column to premises, exposed it on the premise form (nullable, numeric, >= 25 m; empty means no geofence), and shipped shift.geofence = {lat, lng, radius_meters} on GET /api/v1/me/today via a new App\Support\Geofence value object. The block reuses the premise already read for the shift card's label, so it costs no extra query. The demo seeder gives employee@example.com's premise (Sucursal Centro) real Santiago coordinates and a 150 m radius. Verified with 5 new TodayApiTest cases (coords+radius, coords no radius, no coords, no premise, query-count parity) and 3 PremiseManagementTest cases; full suite 705 passed / 4 skipped, pint clean, tsc --noEmit clean.
<!-- SECTION:FINAL_SUMMARY:END -->
