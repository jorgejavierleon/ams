---
id: KOL-53
title: Maintain the legal working-hour limits from the SaaS admin panel
status: To Do
assignee: []
created_date: '2026-08-06 13:17'
labels:
  - overtime
  - frontend
  - backend
  - compliance
milestone: m-2
dependencies:
  - KOL-36
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 150
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The screen where Kolvi staff maintain the versioned legal working-hour limits that KOL-36 models. Split out of KOL-36 so the domain guarantees and the admin UI are reviewable separately.

These limits are global: one row set, consumed by every tenant. That makes this screen unusually consequential for its size — adding a version silently changes what every employer in the system is measured against from that date forward, and getting an effective date wrong misstates payable overtime across the whole customer base at once. The UI should behave accordingly rather than like ordinary CRUD.

What that means concretely:
- Creating a version states plainly, before saving, that it applies to every organization from its effective date.
- A version that has already been used by a calculated day is **not** editable in place. KOL-36 defines a correction flow that recalculates affected days; this screen routes the user into it instead of offering an edit field that would silently rewrite history.
- The timeline is legible at a glance — which limit is in force now, what changed when, what is scheduled to take effect in the future. A staff user should be able to answer "what was the weekly limit in August 2026" by looking, not by querying.

Follow the pattern already established for the other global reference data in the SaaS panel: `app/Http/Controllers/Saas/HolidayController.php` and `resources/js/pages/saas/holidays` are the closest analogue — the same shared DataTable foundation, the `role:saas,saas` middleware on the route group in `routes/web.php`, and Spanish strings under a `saas_*` key in `lang/es/ui.php` alongside `saas_holidays`.

Every change writes to the existing SaaS audit log (`app/Http/Controllers/Saas/AuditLogController.php`, surfaced at `resources/js/pages/saas/audit-log`), because an altered legal limit moves payable figures everywhere and needs to be attributable to a person and a moment.

Nothing tenant-facing is added. Tenant application code reads the resolved limits through the KOL-36 resolver and has no write path at all — the test for that lives here, asserting the refusal rather than only the happy path.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A user holding the `saas` role can list, create and schedule future legal-limit versions from the SaaS admin panel
- [ ] #2 The screen shows the version timeline legibly: what is in force now, what changed when, and what is scheduled to take effect later
- [ ] #3 Creating a version states before saving that it applies to every organization from its effective date
- [ ] #4 A version already used by a calculated day cannot be edited in place; the screen routes the user into the KOL-36 correction flow instead
- [ ] #5 No tenant-facing screen, route or endpoint can create, edit or delete a legal limit; the test asserts the refusal for a tenant admin rather than only the happy path
- [ ] #6 Every create, correction and deletion is recorded in the existing SaaS audit log with the acting staff user and timestamp
- [ ] #7 All strings are in Spanish under a `saas_*` key in the existing translation file, and the list uses the shared DataTable foundation
- [ ] #8 Pest tests cover a saas user creating a version, scheduling a future one, being refused an in-place edit of a used version, a tenant admin refused write access, and the audit entries produced
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
