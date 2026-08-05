---
id: KOL-34
title: >-
  Make POST /api/v1/marks server-authoritative: timestamp, geofence verdict and
  the one-per-day guard
status: To Do
assignee: []
created_date: '2026-08-05 22:10'
updated_date: '2026-08-05 22:16'
labels: []
dependencies:
  - KOL-33
documentation:
  - docs/prd-mobile-app.md
priority: high
type: feature
ordinal: 33000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The mobile app (kolvi-mobile, task KMO-17 "Punch action and the before/working/done state machine") is building the punch button — the single most important interaction in the product, with a p90 time-to-punch target of ten seconds. It cannot be finished against `POST /api/v1/marks` as it stands, and four of KMO-17's acceptance criteria have no server behaviour to verify against.

KOL-33 deliberately excluded this and named it: PRD §6 item 2, haversine at punch time against `Premise.geofence_radius_meters`, persisting `inside|outside|unknown` alongside the reported accuracy.

### The four gaps

1. **`datetime` is required, and it should not exist.** `MarkController::store` validates `'datetime' => ['required', 'date']` and hands it to `MarkManager::createMark`. The mobile design record (kolvi-mobile docs/design-decisions.md §2, '§5 F1 — Timestamps') settled this as **server-authoritative**: the server assigns the legal timestamp and the client never supplies one for an online punch. Res. 38 Art. 11 is the reason — a timestamp a phone can choose is a timestamp a phone can falsify, and the register is the legal record. PRD §399 already lists this as an open question; this ticket is the answer to it.

2. **No accuracy, and no server-side geofence verdict.** The endpoint takes `lat` and `lng` and attaches them after stamping. Nothing measures them against the premise, nothing records how uncertain the fix was, and `Mark` has no column for either verdict or accuracy. The client evaluates the geofence too, but that evaluation is **advisory only and must never be treated as the answer** — a phone that has been in a lift for thirty seconds is wrong about where it is.

3. **No one-in-one-out guard.** `MarkManager::createMark` will happily record a third punch. Decision D-F1-b keeps exactly one `in` and one `out` per day, and `TodayController::punchState` already derives `before|working|done` from exactly that rule — so today the read side and the write side disagree. KMO-17 #7 needs the refusal to be something the app can recognise and render as a friendly Spanish state, never as an error dialog.

4. **`MarkResource` emits an offset-stamped datetime.** `\$this->date_time->toIso8601String()` produces `2026-08-05T08:03:11-04:00`. Datetimes on the wire to this app are naive Santiago wall-clock strings (`YYYY-MM-DD HH:mm:ss`); the client's parser rejects an offset at the boundary on purpose, because a legally-binding timestamp silently re-read in the device's timezone is how attendance shifts by an hour twice a year.

### Contract the mobile client expects

Request — the four location keys are always present, and an explicit `null` is the app saying 'there was no fix', not a key it forgot:

```json
{ "type": "in", "lat": -33.4569, "lng": -70.5975, "accuracy_m": 12.4, "geo_status": "inside" }
```

Response (201):

```json
{ "mark_id": 1841, "hash": "9f2c…", "datetime": "2026-08-05 08:03:11", "type": "in", "geo_status": "inside" }
```

The authoritative reading is `src/features/marcaje/punch-api.ts` in kolvi-mobile — that parser is what a response is graded against, the same way `today-api.ts` was for KOL-31 and KOL-33.

Notes on the shape:

- `geo_status` **on the request is advisory and must never decide the stored verdict.** Accept it, and evaluate independently. The one thing it is good for is corroboration; the server can derive the same answer from `lat`/`lng` being null.
- `unknown` is a first-class verdict and not a failure: no fix, no premise coordinates, or no radius configured. **None of them may block the punch** (D-F1-c, and PRD §283 — an employee who denies location permission must still be able to punch, or attendance becomes unrecordable, which is a legal problem rather than a product one).
- Geolocation must stay **outside the integrity checksum**, as it is today — attached after `MarkObserver` stamps the mark, not folded into it.
- Removing `datetime` from the accepted body is safe on compatibility grounds: the mobile app is the only client and has not shipped a punch. Rejecting it rather than ignoring it is deliberate — a client that keeps sending one should find out immediately, not silently have it discarded.

### What not to build here

**Receipt completeness** — the name, RUT, date, time and folio Res. 38 Art. 13 requires on a comprobante, and decision D-F2-a's real `YYYYMMDD-NNNN` folio. That is PRD §420 item 4, it belongs to kolvi-mobile KMO-19, and it is a separate decision with its own legal reading. This ticket adds only `geo_status` to the resource, because KMO-17 needs the out-of-range line on the receipt it opens.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 POST /api/v1/marks no longer accepts a client-supplied datetime, rejects a body containing one, and stamps the mark server-side in the employee's own timezone
- [ ] #2 The endpoint accepts lat, lng, accuracy_m and geo_status, each nullable, and an explicit null is accepted rather than treated as a missing field
- [ ] #3 The server evaluates the geofence itself at punch time by haversine against Premise.geofence_radius_meters, and the client-reported geo_status never decides the stored verdict
- [ ] #4 The stored verdict is inside, outside or unknown on the mark, and unknown covers no fix, no premise coordinates and no configured radius alike
- [ ] #5 No verdict ever blocks the punch — an out-of-range or unknown punch is recorded and flagged, and still returns 201
- [ ] #6 The reported accuracy is persisted in metres alongside the verdict
- [ ] #7 Geolocation stays outside the integrity checksum, attached after the mark is stamped as it is today
- [ ] #8 MarkResource emits datetime as a naive Santiago wall-clock string YYYY-MM-DD HH:mm:ss, never ISO 8601 with an offset, and carries geo_status
- [ ] #9 Pest feature tests cover a punch inside the radius, outside it, with no fix, at a premise with no radius, and both duplicate refusals
- [ ] #10 The demo seeder leaves employee@example.com able to punch in and out end to end, so the kolvi-mobile Maestro flow can drive the whole state machine
- [ ] #11 A second in, or a second out, on the same day is refused with 409 Conflict and a Spanish message — the mobile client keys on that status alone, since ApiError keeps only message and Laravel's errors bag and a body code would not survive its transport layer
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
