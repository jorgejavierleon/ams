---
id: KOL-56
title: Remove the tenant-wide default overtime compensation type
status: Done
assignee:
  - '@jorge'
created_date: '2026-08-08 11:32'
updated_date: '2026-08-08 16:49'
labels:
  - compliance
milestone: m-2
dependencies: []
priority: high
ordinal: 37000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
KOL-37 added a per-tenant setting, overtime_default_compensation_type, letting an admin choose between payment in payroll and additional rest days as the organization default. Reading the source it cited, that setting should not exist.

Resolución 38 art. 43 (docs/context/resolucion_38.txt:531) requires the system to OFFER both options, which is a capability requirement, not a configuration one. It then closes: 'Si no hubiere pacto escrito que indique lo contrario, las horas extraordinarias se entenderán efectuadas de acuerdo con lo indicado en la letra a) precedente' — that is, payment. The fallback is a legal consequence of the absence of a written agreement, not an employer preference, and an admin can no more change it than they can change the 50 percent surcharge.

The agreement is also per worker, not per organization. Art. 45.3 says 'si las partes hubieren acordado' and requires reporting 'la cantidad de horas compensables de cada dependiente'; art. 41 letter i requires the worker request menu to offer 'compensación de horas extraordinarias por días de descanso adicional'. Compensation therefore belongs to the pacto, which KOL-47 builds.

The concrete hazard while the setting exists: an admin sets the organization to rest days, and a worker with no pacto gets compensated in time off instead of paid — precisely what art. 43 forbids by default, and to the worker's detriment.

Nothing here is deployed, so the column comes out of the KOL-37 migration in place rather than through a new drop migration. The database is refreshed by hand afterwards.

The OvertimeCompensationType enum has no consumer left once the setting goes and is removed with it; KOL-47 reintroduces the vocabulary where it belongs, on the agreement.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The settings table no longer carries a default compensation type, with the column removed from the existing KOL-37 migration rather than dropped by a new one
- [x] #2 No application code reads or writes a tenant-level compensation type, and the OvertimeCompensationType enum is gone along with its translation entries
- [x] #3 The Horas extra section of the organization settings screen no longer offers the choice, and the rest of the section still saves as a whole in one request
- [x] #4 Pest tests covering the removed field are removed or rewritten, and the full suite passes
- [x] #5 KOL-47 is amended so it derives compensation from the worker written agreement, with payment as the non-configurable fallback, rather than from a tenant default
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
1. Remove the column from the KOL-37 migration in place (nothing deployed; DB refreshed by hand).
2. Strip the key from Setting (docblock, fillable, attributes, casts), SettingFactory, OrganizationSettings (drop overtimeDefaultCompensationType) and SettingController (render, options prop, validation).
3. Remove the select from organization-settings.tsx and the label plus overtime_compensation_types entries from lang/es and lang/en.
4. Delete app/Enums/OvertimeCompensationType.php — no consumer remains; KOL-47 reintroduces it on the agreement.
5. Rewrite the affected tests in OrganizationSettingsTest and run the full suite, pint and types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Removed the column from the KOL-37 migration in place rather than by a new drop migration — nothing is deployed, so the database is refreshed by hand instead. Anyone with an existing local database must run a migrate:fresh; the column lingers otherwise.

Also deleted app/Enums/OvertimeCompensationType.php: after the setting went, it had no consumer left. Its vocabulary (payment / rest_days) and its lang entries are what KOL-47 reintroduces on the agreement, so nothing is lost. Its docblock was already legally correct — 'rest days only apply when the parties agreed to them' — which is precisely why the value belongs to the pacto and not to a tenant row.

Guard against regression: tests/Feature/OrganizationSettingsTest.php asserts the settings table has no overtime_default_compensation_type column and that neither the prop nor the select options reach the page.

Checked while here, at the user's request: overtime_weekly_anomaly_threshold_hours is NOT a legal figure and is correctly per tenant. The legal ceilings already live globally and date-versioned in legal_hour_limits (max_overtime_daily_hours 2 per art. 31, max_overtime_weekly_hours 12) per KOL-36, which no tenant can edit. The threshold only decides when a week is flagged for review and blocks nothing, and its default of 10 sits below the legal 12 so the signal fires before the cap. Left as is. A possible future nicety, not done: show the legal ceiling next to the field so nobody reads 10 as the limit.

PRD §10 corrected: the 'Default compensation type' bullet is struck through with the reasoning, as §7.2 was for KOL-38.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Removed the per-tenant overtime_default_compensation_type setting and the now-orphaned OvertimeCompensationType enum. Resolución 38 art. 43 requires systems to *offer* both compensation modes but fixes the fallback as law — absent a written pacto the hours are paid — so it is not an employer preference, and the pacto is per worker (art. 45.3, art. 41 i), leaving no organization-level answer to store. The hazard removed: an admin could set the organization to rest days and a worker with no pacto would be compensated in time off instead of money. Column taken out of the KOL-37 migration in place, nothing being deployed. KOL-47 amended to derive compensation from the worker agreement, and PRD §10 corrected.

Verified: 871 Pest tests green, including a guard asserting the column is absent from the settings table and that neither the prop nor the select options reach the page; Pint and types:check clean; migrate:fresh --seed confirms settings now carries only the four legitimate overtime keys.
<!-- SECTION:FINAL_SUMMARY:END -->
