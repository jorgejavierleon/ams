---
id: KOL-37
title: >-
  Configure the overtime policy per tenant: mode, pacto requirement, thresholds
  and compensation
status: To Do
assignee: []
created_date: '2026-08-06 02:49'
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
- [ ] #1 An organization can set its authorisation mode to pre-authorisation, post-hoc or combined, and the value is readable by calculation code without a database query per read
- [ ] #2 An organization can set whether a written pacto is required, the weekly volume anomaly threshold, the retroactive request window and the default compensation type
- [ ] #3 Defaults for a brand-new organization are: post-hoc mode, no pacto required, 10h weekly threshold, and payment as compensation, matching the Resolución 38 art. 43 assumption that payment applies absent a written agreement
- [ ] #4 The settings are edited from the organization settings screen in Spanish, by a user holding the right permission
- [ ] #5 Changing a setting invalidates the cached settings for that organization and not for any other
- [ ] #6 Settings are organization-scoped: one tenant can never read or write another tenant policy
- [ ] #7 Pest tests cover the defaults on a fresh organization, each mode round-tripping through the settings service, and tenant isolation
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
