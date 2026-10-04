---
id: KOL-142
title: Improve landing page UX/UI for lead conversion
status: To Do
assignee: []
created_date: '2026-10-04 09:40'
labels:
  - frontend
  - marketing
dependencies: []
ordinal: 157000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Conversion-focused UX/UI review of the marketing landing page ('/' route, resources/js/pages/landing.tsx, built in KOL-139). The page has strong, specific copy but is missing standard B2B conversion levers: social proof, benefit-framed stats, a low-friction conversion path, a mid-page CTA, and mobile navigation. See subtasks for the individual, independently-shippable improvements. Goal: raise the demo-request conversion rate without diluting the existing compliance-focused positioning.
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
