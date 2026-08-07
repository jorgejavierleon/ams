---
id: KOL-53
title: Maintain the legal working-hour limits from the SaaS admin panel
status: Done
assignee: []
created_date: '2026-08-06 13:17'
updated_date: '2026-08-07 14:53'
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
- [x] #1 A user holding the `saas` role can list, create and schedule future legal-limit versions from the SaaS admin panel
- [x] #2 The screen shows the version timeline legibly: what is in force now, what changed when, and what is scheduled to take effect later
- [x] #3 Creating a version states before saving that it applies to every organization from its effective date
- [x] #4 A version already used by a calculated day cannot be edited in place; the screen routes the user into the KOL-36 correction flow instead
- [x] #5 No tenant-facing screen, route or endpoint can create, edit or delete a legal limit; the test asserts the refusal for a tenant admin rather than only the happy path
- [x] #6 Every create, correction and deletion is recorded in the existing SaaS audit log with the acting staff user and timestamp
- [x] #7 All strings are in Spanish under a `saas_*` key in the existing translation file, and the list uses the shared DataTable foundation
- [x] #8 Pest tests cover a saas user creating a version, scheduling a future one, being refused an in-place edit of a used version, a tenant admin refused write access, and the audit entries produced
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
1. Routes under the existing role:saas,saas group: index, create, store, correct (GET), update (PUT correction). No destroy — KOL-36 refuses deletion outright.
2. LegalHourLimitController: index renders the whole timeline chronologically with effective_until, in-force/scheduled/superseded status and the count of calculated days judged against each version; create/store append through LegalHourLimitVersions::add with a required acknowledgement that the version applies to every organization; correct/update route into CorrectLegalHourLimit with its mandatory written reason.
3. Audit: log 'created' inside LegalHourLimitVersions::add causedBy the acting staff user (CorrectLegalHourLimit already logs 'corrected').
4. Frontend: resources/js/pages/saas/legal-hour-limits/{index,create,correct}.tsx on the shared DataTable, a legal-hour-limit-form component, nav link in SaasLayout.
5. Spanish strings under ui.saas_legal_hour_limits.
6. Pest SaasLegalHourLimitManagementTest: saas create, scheduled future version, in-place edit of a used version refused, tenant admin refused every write, deletion refused, audit entries asserted.
7. pint, sa test, wayfinder regen with --with-form, npm run types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as three saas routes (index, create/store, correct/update) plus a nav entry; the timeline is the shared DataTable with a card for what is in force today and a notice for scheduled versions.

Two deliberate departures worth reviewing:
- AC #6 mentions auditing deletions. KOL-36 refuses deletion outright (the model throws, and workdays hold a restrict FK), so there is no deletion to audit and no destroy route was added. Instead the tests assert the refusal: no route named saas.legal-hour-limits.destroy exists, and a direct delete() throws LegalHourLimitIsAppendOnly.
- There is no plain edit route at all, for any version, used or not. The only PUT is the correction endpoint and it requires a written reason, so an in-place edit is unreachable rather than merely hidden; the screen labels the row action 'Corregir' and shows how many calculated days the correction will recalculate.

Version creation was previously unaudited — LegalHourLimitVersions::add() now logs a 'created' entry caused by the acting user, matching what CorrectLegalHourLimit already did.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added the SaaS-only maintenance screen for the global legal working-hour limits: /saas/legal-hour-limits behind role:saas,saas, with the version timeline on the shared DataTable (what is in force today, closed date ranges, scheduled/superseded badges, calculated-day counts), an append form that will not save until the staff user acknowledges the change applies to every organization, and a correction screen that routes a used version into CorrectLegalHourLimit with its mandatory written reason and recalculation. No plain edit route and no destroy route exist, so an in-place edit or a deletion is unreachable rather than merely hidden; deletion therefore produces no audit entry because KOL-36 refuses it outright. LegalHourLimitVersions::add() now logs a 'created' activity caused by the acting staff user, matching the 'corrected' entry the correction action already wrote, and both surface in the existing SaaS audit log. Strings live under ui.saas_legal_hour_limits in lang/es (EN added for parity). Verified by 19 Pest tests in tests/Feature/SaasLegalHourLimitManagementTest.php covering creation, future scheduling, the refused in-place edit of a used version, a tenant admin refused every write path, the route-level guard and absent destroy route, and the audit entries; full suite 795 passed / 4 skipped, pint clean, tsc --noEmit clean.
<!-- SECTION:FINAL_SUMMARY:END -->
