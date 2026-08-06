---
id: KOL-35
title: >-
  Complete the mark receipt for Res. 38 Art. 13: a real folio and the worker
  identity on MarkResource
status: Done
assignee: []
created_date: '2026-08-06 02:44'
updated_date: '2026-08-06 14:32'
labels: []
dependencies:
  - KOL-34
documentation:
  - docs/prd-mobile-app.md
priority: high
type: feature
ordinal: 34000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The mobile app (kolvi-mobile, task KMO-19 "Comprobante bottom sheet") draws the receipt an employee sees immediately after punching. Res. 38 Art. 13 sets its minimum content — date, time, name, RUT and hash — and the design adds `N° comprobante`. The sheet is built entirely from the `POST /api/v1/marks` response and never from client state, because the server-recorded time is the truth; so anything Art. 13 requires that the response does not carry is a row the app cannot draw.

Today `MarkResource` returns `mark_id`, `hash`, `datetime`, `type` and `geo_status`. Three of KMO-19's acceptance criteria have nothing to render.

KOL-34 deliberately scoped this out and named it: PRD §420 item 4, and the folio is its own decision (kolvi-mobile docs/design-decisions.md D-F2-a).

### The good news: the legal snapshot already exists

`MarkObserver::creating` already stamps the immutable Art. 13 identity onto every mark — `employee_name`, `employee_rut`, `employer_name`, `employer_rut`, `premise_name`, `premise_address` — precisely so a receipt reprinted years later shows who the employee was *then*, not who they are now. **None of that needs building.** It needs exposing: `MarkResource` simply does not send it.

So the only new data in this ticket is the folio.

### The folio

D-F2-a settled it: **a real folio, not a formatted `mark_id`.** Format `YYYYMMDD-NNNN`, labelled `N° comprobante` on the receipt. The reasoning is that a receipt number an employee reads aloud to HR over the phone has to be short, stable and unambiguous, and Art. 20a expects receipt-to-database consistency.

`marks` has no such column. It needs one, generated at creation like the checksum beside it, sequential per organization per day, and unique. Two details that matter more than they look:

- **Concurrency.** Two employees punching in the same second at the same premise must not receive the same folio. A read-then-increment in PHP will collide on a shift change, which is exactly when the punches arrive. A unique index is the floor; how the number is allocated under it is the implementer's call.
- **Existing marks.** Every mark already in the register has no folio, and a receipt without a `N° comprobante` is one Art. 13 does not cover. Backfilling deterministically — ordered by date and id within each organization — keeps the register whole. Whether that is worth doing is a judgement call worth making explicitly rather than by omission.

### Contract the mobile client expects

Added to the existing 201 body, nothing removed:

```json
{
  "mark_id": 1841,
  "folio": "20260805-0042",
  "hash": "9f2c…",
  "datetime": "2026-08-05 08:03:11",
  "type": "in",
  "geo_status": "inside",
  "employee_name": "Camila Rojas",
  "employee_rut": "12345678-9"
}
```

Notes on the shape:

- `employee_rut` travels **undotted with its verifier digit**, as `users.rut` holds it. The app dots it for display through its own `formatRut` — there is one spelling of a Chilean RUT in that codebase and it is not the server's job to choose it.
- `datetime` stays the naive Santiago wall-clock string KOL-34 made it. The app splits it into the design's `Fecha` (dd/mm/aa) and `Hora` (with seconds, per Art. 13) itself.
- The employer identity is on the mark but is **not** needed here — the design's sheet does not show it. Left out deliberately rather than forgotten; the snapshot is there when a surface wants it.
- Unknown keys are ignored by the client, so adding these breaks nothing already shipped.

### The emailed copy

Art. 12 covers the copy `MarkObserver::created` already mails. If the folio is the number an employee quotes to HR, the receipt in their inbox and the sheet on their phone must show the same one — a folio that exists only in the API response would be the one place the two disagree.

### Worth knowing, and not this ticket's to settle

kolvi-mobile KMO-19 says the hash is copyable *"so the employee can verify it against the public validation endpoint"*. That endpoint — `marks/validate` in `routes/web.php` — is **not public**: it sits inside the DT inspector portal, behind authentication and `password_expires`. Either the mobile copy is describing something that does not exist for employees, or a genuinely public validation route is missing. Flagged here because this ticket is where the hash's purpose comes up; the decision belongs with whoever owns KMO-19.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A folio column exists on marks, unique, in the format YYYYMMDD-NNNN, sequential per organization within each day
- [x] #2 The folio is assigned at creation alongside the checksum, so no mark can exist without one
- [x] #3 Concurrent punches never receive the same folio, enforced at the database level rather than only in PHP
- [x] #4 Marks that predate this change are backfilled deterministically, ordered by date and id within each organization, so no mark in the register lacks a receipt number
- [x] #5 MarkResource returns folio, employee_name and employee_rut alongside the fields it already sends, and removes none of them
- [x] #6 employee_rut is sent undotted with its verifier digit, exactly as users.rut holds it, and is not formatted server-side
- [x] #7 The values come from the snapshot stamped on the mark, never from the live user record, so a reprinted receipt shows who the employee was at the time of the punch
- [x] #8 The emailed receipt from MarkObserver::created shows the same folio as the API response
- [x] #9 Pest feature tests cover the folio format, its per-organization daily sequence, a concurrent-creation collision, and the resource carrying the snapshot rather than the live user
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
1. Add mark_folios counter table (organization_id, folio_date, last_number) with unique(organization_id, folio_date) — the DB-level allocation point.
2. Add App\Support\Folio: insertOrIgnore the counter row, then increment it under lockForUpdate inside a transaction, returning YYYYMMDD-NNNN. Portable, no read-then-write race.
3. Add marks.folio (nullable), backfill existing rows deterministically per organization ordered by date_time then id (seeding mark_folios so new punches continue the sequence), then make the column non-nullable and add unique(organization_id, folio).
4. MarkObserver::creating assigns the folio beside the checksum, after date_time is resolved and after BelongsToOrganization has stamped organization_id (traits boot before attribute observers).
5. MarkResource adds folio, employee_name, employee_rut from the mark snapshot; removes nothing. RUT travels undotted as users.rut holds it.
6. MarkCreated mail + mark-created.blade.php + es/en lang show the same folio (N° comprobante).
7. Pest tests: folio format, per-organization daily sequence, DB-level uniqueness under concurrent creation, resource carries the snapshot not the live user, email shows the same folio, backfill migration.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
**The open question in the description is settled: no public validation route.** The user's call was to drop the promise from the mobile copy rather than build one, so nothing in this ticket changes and no public route is needed. `marks/validate` stays inspector-only. kolvi-mobile KMO-19's description was corrected to match.

Implemented. Folio allocation lives in App\Support\Folio::allocate(): insertOrIgnore the per-organization-per-day counter row in mark_folios, then increment it under lockForUpdate. Portable (no MySQL-only upsert trick) and race-free; marks' unique index on (organization_id, folio) is the floor beneath it. Uniqueness is per organization by design — two organizations issue 20260805-0001 on the same day.

Backfill ran against the dev register: 204/204 marks numbered, 204 distinct folios, contiguous 0001..N per organization per day ordered by date_time then id, and 12 mark_folios counter rows seeded to each day's last number so the next punch continues rather than collides. Soft-deleted marks are numbered too — skipping them would renumber everything after them.

The folio sequence widens past four digits rather than wrapping (Folio::PATTERN allows NNNN+), so an organization punching more than 9,999 times in a day keeps both ordering and uniqueness.

Note: composer types:check fails under Sail with a /tmp/phpstan cache write permission error (environmental). Run on the host it passes with 0 errors.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added marks.folio, the YYYYMMDD-NNNN receipt number Res. 38 Art. 13 needs, and exposed the Art. 13 worker identity the observer was already stamping. App\Support\Folio::allocate() takes the next number from a per-organization-per-day counter row in mark_folios under lockForUpdate, with a unique index on marks (organization_id, folio) beneath it — uniqueness is per organization because two organizations legitimately issue 20260805-0001 on the same day. MarkObserver stamps the folio beside the checksum; MarkResource now returns folio, employee_name and employee_rut (undotted, from the mark's snapshot rather than the live user) alongside everything it already sent; the emailed receipt shows the same folio. Verified with 13 new Pest tests (9 in MarkFolioTest, 4 in MarkApiTest) covering the format, the per-organization daily sequence, DB-level rejection of a duplicate folio and of a folio-less insert, the snapshot surviving a later rename, and the email matching the API; full suite 746 tests / 742 passed / 4 skipped / 0 failures, pint clean, phpstan 0 errors. The backfill ran against the dev register: 204/204 marks numbered, contiguous per organization per day ordered by date_time then id, with 12 counter rows seeded so the next punch continues the sequence.
<!-- SECTION:FINAL_SUMMARY:END -->
