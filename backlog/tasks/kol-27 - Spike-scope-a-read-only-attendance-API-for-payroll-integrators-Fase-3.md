---
id: KOL-27
title: 'Spike: scope a read-only attendance API for payroll integrators (Fase 3)'
status: To Do
assignee: []
created_date: '2026-08-04 11:16'
labels:
  - payroll-reports
  - api
  - spike
dependencies: []
documentation:
  - docs/prd-reports.md
priority: medium
type: spike
ordinal: 26000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Placeholder holding RF-5 so it stays visible without being planned in detail before it is needed. **Do not start building endpoints from this task** — its output is a decision and, if the decision is yes, a set of real tasks.

PRD section 10 says to validate demand before committing, and section 11 puts this in Fase 3 at 1-2 weeks. The counter-argument in the same PRD is real: GeoVictoria competes on exactly this, and section 2.3.3 documents their API in enough detail to copy the shape (Login, AttendanceBook, Consolidated, Consolidated/Extended). An API is also an open-ended maintenance and documentation commitment, which is why it is not in the MVP.

What this spike should settle:
- Do actual clients or prospects want API access, or do they want a file their accountant can import? Ask before building.
- If yes: per-tenant API keys are new work. Sanctum is already in use but scoped to employee device tokens for the mobile app (see KOL-5 through KOL-8 and `app/Http/Controllers/Api`), which is a different auth model from a server-to-server tenant key.
- Rate limiting exists for the mobile API from KOL-8 and would need a tenant-keyed equivalent.
- The endpoint payloads are largely already built by then: KOL-13's aggregation service was deliberately shaped after `Consolidated/Extended` with Spanish field names, so an API would mostly be a serialization layer over work already done.
- The audit requirement in the PRD's non-functional section — every API access logged — needs an answer.
- Whether approaching Nubox as an integration partner is worth pursuing commercially in parallel, per section 2.3.1.

Kill this task outright if the answer is that clients want files, not APIs. That is a valid and cheap outcome.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Real client or prospect demand for API access versus file export is checked and the finding written down
- [ ] #2 A go or no-go recommendation is recorded with its reasoning
- [ ] #3 If go: the per-tenant API key model, rate limiting approach, endpoint list and documentation commitment are outlined and split into implementable tasks
- [ ] #4 If no-go: this task is closed with the reasoning preserved, and no endpoints are built
- [ ] #5 The commercial question of approaching Nubox as an integration partner has an explicit answer, even if that answer is not now
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
