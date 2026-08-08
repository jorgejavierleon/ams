---
id: KOL-57
title: Remove the tenant switch that made a pacto mandatory to approve overtime
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-08 15:28'
updated_date: '2026-08-08 16:49'
labels:
  - compliance
milestone: m-2
dependencies: []
priority: high
ordinal: 38000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
KOL-37 added overtime_requires_pact, a per-tenant switch deciding whether an overtime record needs a valid written agreement behind it to become approvable. Nothing reads it yet, and it should not exist in that form.

Art. 32 of the Código del Trabajo requires overtime to be agreed in writing, for at most three months. But the absence of that agreement does not make the hours cease to be overtime: the DT reality criterion, stated by the PRD itself at line 22 — 'even without a written agreement, hours must be paid if the employer had knowledge' — means hours actually worked with the employer knowledge are payable regardless. A switch that makes such a record unapprovable therefore produces an unlawful outcome rather than a conservative one, and it is the tenant, not the law, deciding it.

The correct shape is already in the source material. Res. 38 art. 45.2 says of the excessive-shift alert that it 'no impedirá la carga de la jornada, sino que sólo constituirá un aviso para el empleador'. KOL-41 implements exactly that for the legal caps: validate, flag, demand a written reason, never block. KOL-42 has been amended so a missing pacto follows the same pattern, which leaves this switch with nothing to configure — the flag applies to everyone.

Nothing is deployed, so the column comes out of the KOL-37 migration in place rather than through a new drop migration, exactly as KOL-56 did for the compensation default. The database is refreshed by hand afterwards.

See decision-1 for the standing criterion this applies.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The settings table no longer carries the pacto requirement switch, with the column removed from the existing KOL-37 migration rather than dropped by a new one
- [x] #2 No application code reads or writes a tenant-level pacto requirement, and its translation entries are gone
- [x] #3 The Horas extra section of the organization settings screen no longer offers the switch, and the rest of the section still saves as a whole in one request
- [x] #4 Pest tests covering the removed switch are removed or rewritten, a guard asserts the column is absent, and the full suite passes
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Removed the column from the KOL-37 migration in place, as KOL-56 did — nothing is deployed. Anyone with an existing local database must run a migrate:fresh.

Unlike KOL-56 there was no enum to retire: the switch was a plain boolean. Its Spanish and English labels are gone from lang/*/ui.php.

The tenant-scoping test in OrganizationSettingsTest used this flag as its second differing value between two organizations; it now uses overtime_retroactive_request_days instead, so the isolation assertion still exercises two keys rather than one.

Guard against regression: the suite asserts the settings table has no overtime_requires_pact column and that the prop never reaches the page.

PRD §10 corrected with the reasoning, matching the treatment given to §7.2 (KOL-38) and to the compensation bullet (KOL-56). The behaviour that replaces the switch lives in KOL-42 AC #7: a missing pacto is flagged and approvable with a mandatory written justification, never blocked.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Removed the per-tenant overtime_requires_pact switch. Art. 32 requires overtime to be agreed in writing, but the absence of that agreement does not stop the hours being overtime: the DT reality criterion, stated by the PRD itself, makes hours worked with the employer's knowledge payable regardless, so a switch rendering such a record unapprovable produces an unlawful outcome rather than a conservative one. The replacement behaviour lives in KOL-42 AC #7 — a missing pacto is flagged and approvable with a mandatory written justification, never blocked — following the shape Res. 38 art. 45.2 sets for the excessive-shift alert and that KOL-41 already implements for legal caps. Column taken out of the KOL-37 migration in place; PRD §10 corrected.

Verified: 871 Pest tests green, including a guard asserting the column is absent and the prop never reaches the page, and the tenant-isolation test reworked to exercise two surviving keys; Pint and types:check clean.
<!-- SECTION:FINAL_SUMMARY:END -->
