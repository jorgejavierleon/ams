---
id: KOL-33
title: Carry the premise geofence on GET /api/v1/me/today
status: To Do
assignee: []
created_date: '2026-08-05 10:37'
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
- [ ] #1 A nullable geofence_radius_meters column exists on premises, in metres, and is fillable and cast on the model
- [ ] #2 The premise form accepts and persists the radius, validated as nullable, numeric and at least 25 metres, and leaving it empty means no geofence
- [ ] #3 GET /api/v1/me/today returns shift.geofence as {lat, lng, radius_meters} with lat and lng as JSON numbers, not strings
- [ ] #4 shift.geofence is null when the premise has no coordinates, and radius_meters is null when no radius is configured — neither is an error
- [ ] #5 The geofence block adds no query to the response — the premise is already loaded for the shift card's label
- [ ] #6 A Pest feature test covers a premise with coordinates and a radius, one with coordinates and no radius, and one with no coordinates
- [ ] #7 The demo seeder gives employee@example.com a premise with coordinates and a radius, so the mobile Maestro flow can drive in and out of range with bin/device geo
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
