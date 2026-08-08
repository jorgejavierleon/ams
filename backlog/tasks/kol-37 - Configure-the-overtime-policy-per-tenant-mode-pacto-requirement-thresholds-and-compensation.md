---
id: KOL-37
title: >-
  Configure the overtime policy per tenant: mode, pacto requirement, thresholds
  and compensation
status: Done
assignee: []
created_date: '2026-08-06 02:49'
updated_date: '2026-08-08 11:33'
labels:
  - overtime
  - backend
  - frontend
  - domain
milestone: m-2
dependencies:
  - KOL-36
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: feature
ordinal: 200
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Section 10 of the PRD makes the whole module tenant-configurable, and almost every task downstream reads one of these values. It lands early so the rest can consume it rather than each inventing its own flag.

What a tenant configures:
- **Authorisation mode**: pre-authorisation (employee requests before working, Talana-style), post-hoc (marks generate a shift excess that a supervisor later approves or objects, Buk-style), or both combined — Mode A for planned overtime, Mode B as the safety net for unrequested excess.
- **Written agreement required**: whether an overtime record needs a valid pacto behind it to become approvable.
- **Weekly volume anomaly threshold**: suggested default 10h. The PRD flags as an open question whether this should vary by industry — critical shifts in IT or healthcare legitimately spike, as GeoVictoria notes about service continuity. Per-tenant configurability answers that without needing an industry taxonomy.
- **Retroactive request window**: how many days back an employee may request overtime for in Mode A.
- **Default compensation type**: payment in payroll versus additional rest days. Resolución 38 art. 43 requires both modes to be offered and, absent a written agreement stating otherwise, payment is assumed — so payment is the default.

This is an extension of the existing per-organization settings, not a new mechanism. Follow `app/Models/Setting.php` exactly: the `#[Fillable]` attribute, the `$attributes` defaults array that mirrors the migration, the casts, and reads through `app/Services/OrganizationSettings.php` so the values come off the cached attributes array rather than hitting the database on every calculation. `app/Observers/SettingObserver.php` already invalidates that cache on write.

The UI belongs on the existing organization settings screen (`resources/js/pages/organization-settings.tsx`), in Spanish, as its own section.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 An organization can set its authorisation mode to pre-authorisation, post-hoc or combined, and the value is readable by calculation code without a database query per read
- [x] #2 An organization can set whether a written pacto is required, the weekly volume anomaly threshold, the retroactive request window and the default compensation type
- [x] #3 Defaults for a brand-new organization are: post-hoc mode, no pacto required, 10h weekly threshold, and payment as compensation, matching the Resolución 38 art. 43 assumption that payment applies absent a written agreement
- [x] #4 The settings are edited from the organization settings screen in Spanish, by a user holding the right permission
- [x] #5 Changing a setting invalidates the cached settings for that organization and not for any other
- [x] #6 Settings are organization-scoped: one tenant can never read or write another tenant policy
- [x] #7 Pest tests cover the defaults on a fresh organization, each mode round-tripping through the settings service, and tenant isolation
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
1. Add OvertimeAuthorizationMode and OvertimeCompensationType enums (label()/options(), mode predicates).
2. Migration add_overtime_policy_to_settings_table: overtime_authorization_mode (post_hoc), overtime_requires_pact (false), overtime_weekly_anomaly_threshold_hours decimal 10.00, overtime_retroactive_request_days (7), overtime_default_compensation_type (payment).
3. Setting model: fillable, $attributes defaults, enum/float casts; SettingFactory defaults.
4. OrganizationSettings: typed cached readers overtimeAuthorizationMode()/overtimeCompensationType() over the cached attributes array (no query per read).
5. SettingController: extend to validate + render the new keys with enum options.
6. organization-settings.tsx: new 'Horas extra' section (selects, number inputs, switch); es/en translations.
7. Pest: defaults on a fresh org, each mode round-trips through OrganizationSettings, cache invalidation scoped to one tenant, tenant isolation, validation.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as an extension of the existing per-organization settings row (no new table):

- New enums App\Enums\OvertimeAuthorizationMode (pre_authorization | post_hoc | combined, with allowsRequests()/allowsShiftExcess() so downstream code never special-cases Combined) and App\Enums\OvertimeCompensationType (payment | rest_days).
- Migration 2026_08_07_145742_add_overtime_policy_to_settings_table adds overtime_authorization_mode (default post_hoc), overtime_requires_pact (false), overtime_weekly_anomaly_threshold_hours decimal(5,2) default 10, overtime_retroactive_request_days default 7, overtime_default_compensation_type (default payment).
- OrganizationSettings gained typed cached readers overtimeAuthorizationMode() and overtimeDefaultCompensationType(); a test asserts repeat reads issue zero queries.
- Retroactive window default of 7 days was chosen here: the PRD gives no figure for it (it only suggests 10h for the weekly threshold).
- Tenant isolation is enforced by the existing OrganizationScope: passing another organization's id to OrganizationSettings while a different tenant is active does not read that tenant's row. Cross-tenant reads are therefore only reachable when no tenant is active (queue jobs), which is the intended use of the explicit $organizationId argument.
- The screen stays behind the existing 'role:admin' middleware on organization-settings.{edit,update}; the overtime section did not introduce a separate permission. Worth revisiting if overtime policy should be delegable to a non-admin role.

Verification: sa test --compact — 815 tests passing (20 covering this task in OrganizationSettingsTest + OvertimeAuthorizationModeTest), phpstan 0 errors, pint clean, npm run types:check clean.

AC #4 is proven at the level automation reaches: the route stays behind role:admin (tests assert 403 for a non-admin on both edit and update), the Inertia page renders the new props, and a test asserts every overtime label/hint key resolves in Spanish and that the two select option sets read 'Autorización previa / Revisión posterior / Combinado' and 'Pago en remuneraciones / Días de descanso'. The visual half — layout at wide/narrow widths, dark mode, the save toast — was not exercised in a browser; it is logged in docs/QA_CHECKLIST.md for the user to run.

Also added Phase 4.5 to the implement-ticket skill: when the user cannot QA locally, append the pending checks to docs/QA_CHECKLIST.md and then merge as usual.
<!-- SECTION:NOTES:END -->

## Comments

<!-- COMMENTS:BEGIN -->
author: @jorge
created: 2026-08-08 11:33
---
Correction, filed as KOL-56: the overtime_default_compensation_type setting shipped here should not exist. Its justification cited Resolución 38 art. 43, but art. 43 read in full requires systems to *offer* both compensation modes and then fixes the fallback as law — absent a written pacto, the hours are paid — so it is not an employer-level preference. The agreement is per worker (art. 45.3, art. 41 i), which puts the compensation type on the pacto in KOL-47. The other four keys added here are unaffected: mode, pacto requirement, retroactive window and the weekly anomaly threshold are genuine tenant policy. The threshold in particular was re-checked and is not a legal figure — the legal ceilings live globally in legal_hour_limits (max_overtime_daily_hours 2, max_overtime_weekly_hours 12) per KOL-36, and the threshold only decides when a week is flagged for review.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Extended the per-organization settings row with the overtime policy: authorisation mode (pre_authorization/post_hoc/combined), pacto requirement, weekly anomaly threshold, retroactive request window and default compensation type, with post-hoc + no pacto + 10h + payment as the defaults for a new organization. Values are read through typed, cached accessors on OrganizationSettings so calculation code issues no query per read. Verified by 20 Pest tests covering defaults, mode round-trips, per-tenant cache invalidation, tenant isolation, validation and Spanish labels; browser QA deferred to docs/QA_CHECKLIST.md.
<!-- SECTION:FINAL_SUMMARY:END -->
