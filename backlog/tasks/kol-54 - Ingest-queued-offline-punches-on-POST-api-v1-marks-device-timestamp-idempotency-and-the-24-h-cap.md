---
id: KOL-54
title: >-
  Ingest queued offline punches on POST /api/v1/marks: device timestamp,
  idempotency and the 24 h cap
status: Done
assignee: []
created_date: '2026-08-07 18:14'
updated_date: '2026-08-07 20:07'
labels:
  - mobile
  - api
  - offline
  - compliance
dependencies:
  - KOL-34
  - KOL-35
references:
  - docs/prd-mobile-app.md
  - docs/context/resolucion_38.txt
priority: high
type: feature
ordinal: 35000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The employee mobile app is building its offline punch queue (kolvi-mobile KMO-22/23/24). `POST /api/v1/marks` as KOL-34 left it cannot receive a queued punch: it stamps its own clock, rejects any client time outright, and has no way to tell a retry from a second punch. This is the backend half of that epic, and KMO-23 is blocked on it.

The compliance position was settled by the kolvi-mobile spike KMO-21 and signed off on 2026-08-07. It lives in that repo at `docs/design-decisions.md` §4 (this repo has no copy — read it there; §4.3 is the wire contract). The short version, because it reverses something KOL-34 deliberately built:

### Why a queued punch may carry its own timestamp

KOL-34 made the endpoint server-authoritative and `datetime` `prohibited`, on the reading that a time the phone chooses is a time the phone can falsify (Res. 38 Art. 11). That is still exactly right for an online punch and does not change.

It is wrong for a queued one. Res. 38 Art. 10 is an express exception permitting a system to `capturar y almacenar la correspondiente marca` and transmit later, automatically, on signal recovery. Art. 11 is textually attached to it — `Para cumplir el fin señalado en el párrafo anterior` — and requires the sello de tiempo to be the hour `en que se efectúa una marcación`. Stamping the server's clock on a punch that was made four hours earlier in a basement would register a false hour, against Art. 11, Art. 44 (`precisión de hora, minuto y segundo de cada marcaje`) and Art. 41 b) (no `perjuicio` to the worker).

So: the server still assigns `date_time` on both paths. On the queued path it **adjudicates** it from a validated `device_datetime` rather than trusting it — a bounded window, an explicit refusal, and the raw reading kept beside the legal value permanently. Art. 8 and Art. 14 a) ii) are not in the way; they govern `adulteración post - registro`, not where a timestamp originates.

### What the client sends

Two fields, present only on a queued punch, and a `422` if either appears on an online one or one arrives without the other. `datetime` stays `prohibited` on both paths.

```json
{ "type": "in", "lat": -33.4569, "lng": -70.5975, "accuracy_m": 12.4,
  "geo_status": "inside", "device_datetime": "2026-08-07 08:03:11",
  "idempotency_key": "0f9c4e6a-3b21-4d7f-9a58-1c2e7b40d913" }
```

`device_datetime` is a naive Santiago wall-clock string like every datetime on this wire — never an offset. `idempotency_key` is a UUIDv4 the device generates once when it queues the punch and never regenerates on a retry; it is in the body rather than a header because this endpoint has to persist it to enforce the guarantee.

### The 24 h cap

Measured from `device_datetime` to receipt. Inside it, the queue is a transmission delay. Past it, the regulation's own regularization machinery has already run — Art. 45.1 alerts employee and employer 30 minutes after a missed punch, and Art. 40 f) lets the system fill a missing mark `al día siguiente` — so a late insert becomes a competing version of a record HR may already have acted on.

An over-age punch is therefore neither inserted as a mark nor discarded. It is filed through the Art. 39 b) / Art. 40 addition pathway, the same bilateral procedure HR uses for a forgotten punch, with the Art. 40 email and the employee's 48-hour window to object. `MarkModification` already models that direction of correction; whether this reuses it or needs its own pending-addition record is this task's call to make.

### The checksum question, which is genuinely open

`MarkObserver` hashes `user_id . type . date_time->toIso8601String()`. Because `date_time` is the adjudicated (truthful) time, the Art. 8 checksum already covers it with no formula change and no loss of verifiability for marks already recorded — a property worth keeping.

But `captured_offline` and the raw `device_datetime` would sit outside that envelope, so clearing the flag would leave a valid hash. Art. 8 wants a hash `de los datos de cada operación`. Pulling them inside means a conditional formula, which is a burden on the Art. 8 verification tool the same article requires. Decide it here and write down why — geolocation is already deliberately excluded on the same grounds, so there is a precedent to either follow or depart from.

### Not in scope

The queue, the banner, the receipt and the offline session are the mobile app's (KMO-22/23/24/49). Offline-frequency reporting for the Art. 10 `casos particulares debidamente justificados` justification is real work but belongs in a reporting task, not this one.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 POST /api/v1/marks accepts device_datetime (naive Santiago wall-clock, no offset) and idempotency_key (UUIDv4), and rejects with 422 when either appears on an online punch or when one arrives without the other
- [x] #2 datetime remains prohibited on both the online and the queued path
- [x] #3 For a queued punch the server assigns date_time from the validated device_datetime, and stores the raw reading, the sync time and an offline provenance flag as separate persisted columns on the mark
- [x] #4 An online punch is unchanged: date_time is the server's clock, the provenance flag is false, and KOL-34's tests still pass untouched
- [x] #5 device_datetime outside the accepted window is refused with a 422 carrying a Spanish message the app can show verbatim, and no mark is created
- [x] #6 A unique index on (user_id, idempotency_key) makes a replay impossible to record twice, and the second request answers 200 with a response byte-identical to the original 201's body rather than 201 or an error
- [x] #7 A queued punch whose device_datetime is more than 24 hours old creates no mark and is instead filed as a pending addition through the Res. 38 Art. 39 b) / Art. 40 procedure, including the employee notification and the 48-hour objection window
- [x] #8 The existing one-IN-one-OUT-per-day guard still answers 409 for a queued punch on a day that already holds that type
- [x] #9 A decision is recorded, in code comments and on this task, on whether offline provenance enters the Art. 8 checksum input, with the reasoning either way and the effect on verifying marks recorded before this change
- [x] #10 MarkResource echoes device_datetime, the sync time and the provenance flag so the app can render the provenance on a synced receipt
- [x] #11 Feature tests cover: a queued punch recorded at its device time, a replay answering 200, an over-age punch filed rather than inserted, a rejected window, an online punch unaffected, and the 409 collision
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
1. Migrations: marks gets device_datetime, synced_at, captured_offline, idempotency_key + unique(user_id, idempotency_key); mark_modifications gets device_datetime + captured_offline so a filed queued punch keeps its provenance when it consolidates into a mark.
2. Mark model: fillable, casts, property docs. MarkModification: casts + docs.
3. MarkObserver: stamp synced_at at creation (now() = when the register received it, equal to date_time online); checksum gains a conditional offline suffix so every mark already recorded and every online punch hash exactly as before.
4. MarkManager: getMarkOnDate(type, user, date) with getTodayMark delegating to it (the queued day guard is the device day, not today); createMark gains an $attributes bag so provenance is present before the observer hashes.
5. Api/MarkController::store: device_datetime + idempotency_key validated as a pair (required_with both ways, date_format Y-m-d H:i:s, uuid:4); replay lookup first -> 200 with the original receipt; then the window (future -> 422, >24 h -> filed + 422); then the per-day 409 against the device day; then create with provenance.
6. Action FileQueuedPunchAsAddition: find-or-create the Workday for the device day, then MarkModificationManager::createModification with the device reading -> Art. 39 b)/40 pending addition, employee notified, 48 h window from the existing machinery.
7. MarkResource echoes device_datetime, synced_at, captured_offline.
8. config/ams.php: offline_punch_max_age_hours (24) and offline_punch_future_tolerance_minutes; Spanish + English messages in lang/*/ui.php under marks.api.offline.
9. tests/Feature/Api/OfflineMarkApiTest.php: queued punch at device time, replay -> 200 identical body, over-age filed not inserted, future window rejected, online punch unchanged, 409 on the device day, checksum decision.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## AC #9 — the Art. 8 checksum decision: offline provenance goes INSIDE the envelope, via a conditional formula

Recorded in code on `MarkObserver::checksumInput()` and in docs/architecture.md (Mobile API section).

**The formula.** Online, and for every mark already in the register: `sha256(user_id . type . date_time->toIso8601String())` — byte for byte the string KOL-34 left. Queued: the same string plus `'|offline|' . device_datetime->toIso8601String()`.

**Why inside.** Art. 8 asks for a hash `de los datos de cada operación`. On a queued punch the provenance is part of the operation rather than metadata about it: `date_time` was adjudicated from the device reading instead of read off the server's clock, so a `captured_offline` that could be cleared — or a `device_datetime` that could be rewritten — without breaking the hash would leave the register unable to say how its own legal timestamp was obtained. Art. 10's second paragraph needs exactly that to justify the exception as `debidamente justificado`. Both are covered: the flag by the presence of the suffix, the raw reading by its content.

**Why conditional rather than unconditional.** Every checksum already stored was computed over the three-part string, and a comprobante Art. 13 g) already put in an employee's hands prints it. Folding the new fields in unconditionally would recompute nothing but would make every one of those marks fail a recomputation check — invalidating the existing register to spare a branch. Not a trade Art. 8 permits. Under the conditional formula no stored checksum moves and no printed proof stops matching.

**Effect on marks recorded before this change.** None. `captured_offline` defaults to false, so every existing row takes the first branch and verifies exactly as it did.

**Cost, and who pays it.** One conditional in any Art. 8 verification tool. This codebase's own — `Dt\\MarkValidationController`, the fiscalizador page — pays none of it: an inspector's checksum is *looked up* (`Mark::where('checksum', ...)`), never recomputed, so a conditional input never reaches it. The burden falls only on a future recomputing verifier, and it is one branch on a stored boolean.

**Precedent.** Geolocation stays outside, as KOL-34 left it, and the two are not the same case: a coordinate is a measurement *about* the punch, legitimately absent, attached after the mark is stamped. The provenance of the legal timestamp is the punch.

## Other decisions worth carrying

- **The over-age pathway reuses `MarkModification`** rather than a new pending-addition model (the task left this open). It already models an addition — `mark_id` null, `mark_type` set — and already carries the whole Art. 40 apparatus: the employee notification, `notified_at`, the 48 h window from `ams.mark_modification_timeout_hours`, and the scheduled consolidation on silence. Reason is `SystemError`, which is what Art. 39 b)'s `fallas del sistema` is. A parallel model would have had to grow all of that again.
- **`mark_modifications` gained `device_datetime` + `captured_offline`.** Approving an addition creates the mark, so without carrying provenance across the mark that eventually lands looks like an ordinary punch and the register can no longer say which of its marks were queued — the Art. 10 second-paragraph problem again, one step later.
- **The window has two edges and they are not symmetric.** Older than 24 h is filed (`code: queued_punch_too_old`); ahead of the server beyond `offline_punch_future_tolerance_minutes` (5) is simply refused (`code: queued_punch_in_future`), because there is no missing mark to add — the phone is wrong about what time it is, and the fix is on the device.
- **Both refusals answer 422 with `{message, code}`.** `message` is the Spanish the app shows verbatim (`ui.marks.api.offline.*`); `code` is what it branches on. Half a pair, a bad UUID, an offset-bearing `device_datetime` or a `datetime` on either path stay ordinary Laravel validation 422s.
- **The one-per-day guard now keys off the day the punch was *made*** (`MarkManager::getMarkOnDate`), not today — a punch queued at 23:40 and synced the next morning collides with yesterday.
- **The replay lookup runs before the window and before the 409.** A punch already in the register is recorded, whatever the queue has since become old enough for.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
POST /api/v1/marks now ingests a queued offline punch: the device_datetime + idempotency_key pair is validated together, the server adjudicates date_time from the phone's reading inside a 24 h window and keeps the raw reading, sync time and captured_offline flag beside it, a replay answers 200 with the original receipt behind a unique (user_id, idempotency_key) index, and an over-age punch is filed as an Art. 39 b) addition through MarkModification — notified, with the 48 h window — rather than inserted or discarded. Offline provenance enters the Art. 8 checksum through a conditional suffix, so every mark recorded before this change still verifies against the string it was hashed with. The online path is byte-for-byte unchanged.
<!-- SECTION:FINAL_SUMMARY:END -->
