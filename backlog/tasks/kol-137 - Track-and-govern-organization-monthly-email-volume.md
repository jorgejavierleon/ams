---
id: KOL-137
title: Track and govern organization monthly email volume
status: To Do
assignee: []
created_date: '2026-10-01 09:29'
updated_date: '2026-10-01 09:31'
labels:
  - saas
  - email
  - admin
dependencies: []
priority: medium
type: feature
ordinal: 147000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Today no outgoing email is tracked anywhere in the app (grepped app/Mail, app/Notifications — ~19 classes, nothing records a send). A platform admin in the existing SaaS panel (`App\Http\Controllers\Saas\OrganizationController`, `/saas/organizations`) needs to see how many emails each organization sends per month, cap that usage, and have the cap suggested from a per-user baseline rather than guessed per organization.

Scope, decided with the requester:
- Every outgoing email counts, regardless of type (document signing, leave/overtime, password reset, DT audit mail, etc.) — not just user-triggered transactional mail.
- Two independent thresholds per organization: a **soft limit** (alert platform admins, keep sending) and a **hard limit** (stop sending entirely for that organization until the next calendar month or until a platform admin raises it).
- A platform-wide, admin-editable **baseline**: expected emails per user per month. No fixed number is being hardcoded — this ticket group ships the *setting*, not a guessed default value.
- Each organization's soft and hard limit independently default to `active_users_count × baseline` and can be overridden per organization. Overrides persist even if the baseline later changes.
- Hard-limit blocking is total: once hit, nothing more sends for that organization this month, with no carve-out for time-sensitive mail (password reset, signature codes). Simplicity was chosen deliberately over partial exceptions.

Split into three vertical slices so each is independently reviewable and human-testable in the SaaS panel:
1. KOL-137.1 — record every send and show monthly volume per organization (the walking skeleton: no limits yet, just visibility).
2. KOL-137.2 — the baseline setting and per-organization soft/hard limit configuration (no enforcement yet).
3. KOL-137.3 — actually enforcing the two thresholds.

Sub-tasks share one subsystem (the mail-sending pipeline + the SaaS panel) and one goal (organization email-usage governance), hence grouped under this parent rather than as independent dependency-linked tasks.
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
