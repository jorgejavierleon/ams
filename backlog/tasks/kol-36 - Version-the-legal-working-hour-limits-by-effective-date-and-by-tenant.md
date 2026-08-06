---
id: KOL-36
title: >-
  Model the legal working-hour limits as date-versioned values that keep the
  past stable
status: To Do
assignee: []
created_date: '2026-08-06 02:48'
updated_date: '2026-08-06 13:18'
labels:
  - overtime
  - backend
  - domain
  - compliance
milestone: m-2
dependencies: []
documentation:
  - docs/PRD_Overtime_Module_Kolvi_EN.md
priority: high
type: task
ordinal: 100
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Every legal limit this module validates against is currently either hardcoded or wrong. `config/ams.php` declares `max_weekly_hours => 45` and `max_daily_hours => 10` — the 45 has been stale since Ley 21.561 took the ordinary workweek to 42 hours on 26 April 2026, and it keeps falling to 40 by 2028.

This task is the domain: the versioned limits themselves, the resolver, and the guarantees that keep the past stable. The SaaS admin screen for maintaining them is KOL-53.

## Why a constant is not merely stale but actively wrong

Take a week in August 2026: the employee works 40 hours against a 42-hour week and the report shows 2 hours under. In 2028 the limit becomes 40 and the same week is reprinted. Reading a single scalar, the report now says that week was exactly on target — the deficit disappears, and a closed period gives a different answer than it gave the first time. A report that changes its mind about the past is not defensible in front of an inspector, and it is not reconcilable against a payroll run that already happened.

So the limits are **date-versioned**, and four properties have to hold together for that to actually work. Each is testable and each is easy to lose accidentally:

**1. Versions are append-only.** A new law adds a row with its own effective-from date. The 42-hour row is never edited to say 40 — editing in place destroys the past exactly as a scalar does, only less visibly. A genuine data-entry error in a version needs an explicit correction path that recalculates every affected day, not a quiet update.

**2. The resolver takes a date and has no default.** There is no `current()` and no implicit `now()`. This is the structural guard for the whole bug class, which always looks like someone reaching for the newest row when they meant the applicable one. If the only way to obtain a limit is to say which date it is for, the property holds by construction instead of by everyone remembering.

**3. Resolve and stamp, both.** The figure is resolved from the date at calculation time, and the version used is stamped onto the calculated day. The stamp is not what drives the number — it is what proves which rule was applied, and what surfaces drift if the two ever disagree.

**4. A transition mid-week needs a written rule.** Ley 21.561 took effect on Sunday 26 April 2026, landing almost cleanly on a week boundary; the next change may not. If a limit changes on a Wednesday, the week it falls in is judged against one value or the other, and which one is a decision to record with its reasoning — not something to leave to whatever the query happens to do. The daily caps have no such problem. KOL-41 defines the week itself and must agree with whatever is decided here.

## What needs to be resolvable for an arbitrary date

- ordinary weekly hours (45 → 42 from 2026-04-26 → 40 from 2028, per Ley 21.561)
- maximum overtime per day (2h, Código del Trabajo art. 31)
- maximum overtime per week (12h)
- maximum ordinary + extraordinary per day (12h) and per week

## Ownership: global, not per tenant

**These are global.** This is a deliberate departure from PRD section 9, which lists legal-cap parameters as per-tenant configurable. The reasoning: these values *are* the law of Chile, identical for every employer in the country. A tenant able to edit them is a tenant able to quietly raise their own overtime ceiling and have Kolvi endorse the resulting figure in an audit — the exact failure this module exists to prevent. What genuinely varies per tenant is policy, not law, and that lives in KOL-37: authorisation mode, pacto requirement, volume thresholds, compensation type.

Tenant application code reads the resolved limits and has no write path. Maintenance happens in the SaaS panel (KOL-53).

Deliberately **out of scope**: a per-tenant exception for an employer holding a DT-authorised jornada excepcional (art. 38). That case is real but rare, and building an override now would reintroduce the tenant-editable surface this task exists to remove. If it comes up, the shape to reach for is a Kolvi-staff-set exception in the SaaS panel — never tenant self-service.

Audit the existing consumers of `config('ams.max_weekly_hours')` and `config('ams.max_daily_hours')` before changing anything — shift validation already reads them, and this task must not change what that screen reports for a shift saved today.

Reference: docs/PRD_Overtime_Module_Kolvi_EN.md sections 5 and 9.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Ordinary weekly hours, daily and weekly overtime caps, and the combined ordinary-plus-extraordinary ceilings are resolvable for any given date rather than read from a constant
- [ ] #2 The seeded baseline reflects the real timeline of Ley 21.561: 45h until 2026-04-25, 42h from 2026-04-26, 40h from its statutory date
- [ ] #3 The resolver requires an explicit date and offers no current-value accessor and no implicit default to today, so a historical calculation cannot silently pick up the newest version
- [ ] #4 Limit versions are append-only: a new law adds a version and never edits an existing one, and there is no path by which editing a version silently changes an already-calculated day
- [ ] #5 A correction to a mistaken version is possible only through an explicit flow that recalculates every day it affected, rather than as a quiet update
- [ ] #6 Each calculated day is stamped with the limit version it was judged against, and a mismatch between the stamp and what the date would resolve to today is detectable
- [ ] #7 Adding a new future version leaves every previously calculated and previously reported figure byte-for-byte unchanged; the regression test reprints a past period before and after adding a version and asserts identical output
- [ ] #8 The rule for a week straddling a limit change is decided, implemented and documented in the notes with its reasoning, and agrees with the week definition KOL-41 establishes
- [ ] #9 Tenant application code can read the resolved limits it needs and has no write path to them
- [ ] #10 Every existing consumer of the two `config/ams.php` hour constants is migrated, and shift validation reports the same result it did before for a shift saved today
- [ ] #11 Pest tests cover a date before and after a limit change, a date with no version defined, a recalculation of an old day after a newer version exists, and a week straddling a change
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
