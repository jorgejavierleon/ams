---
id: KOL-144
title: Add a honeypot field to the landing page's lead contact form
status: Done
assignee:
  - jorgejavierleon@gmail.com
created_date: '2026-10-04 21:22'
updated_date: '2026-10-04 21:24'
labels:
  - frontend
  - backend
dependencies: []
ordinal: 166000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The public contact form (POST /leads, app/Http/Controllers/LeadController.php) already has throttle:10,1 on the route (KOL-139) but no bot-specific defense. Add a standard honeypot field: a hidden, bot-only input in the form that real users never see or fill. If it arrives non-empty, silently drop the submission (no Lead row created) and respond exactly like a successful submission, so bots don't learn to avoid the field.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The contact form on the landing page includes a honeypot input that is not visible or reachable by a real user (visually hidden off-screen, not display:none, tabIndex -1, excluded from tab order and screen readers)
- [x] #2 POST /leads with the honeypot field filled does not create a Lead row and still responds with a redirect indistinguishable from a normal successful submission
- [x] #3 POST /leads with the honeypot field empty (the normal case) behaves exactly as before
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Backend: in app/Http/Controllers/LeadController.php, validate an optional 'website' field, then before creating the Lead check if it's filled — if so, return back() without persisting (silent drop, identical response to success).
2. Frontend: in resources/js/pages/landing.tsx LeadContactForm, add 'website' to the useForm() initial data, and render a visually-hidden (off-screen positioning, not display:none/visibility:hidden), tabIndex={-1}, autoComplete='off', aria-hidden input bound to it inside the form.
3. Tests: add to tests/Feature/LandingPageTest.php — a submission with the honeypot filled does not create a Lead and still redirects; confirm existing empty-honeypot tests still pass unchanged.
4. Run pint --dirty, types:check, eslint, and the LandingPageTest suite.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented: LeadController validates an optional 'website' honeypot field and silently returns back() without creating a Lead when it's filled. landing.tsx's LeadContactForm renders the honeypot off-screen (absolute left-[-9999px], aria-hidden, tabIndex=-1, autoComplete=off) inside the existing form. Added test 'a filled honeypot silently drops the submission' to LandingPageTest.php. Verified in browser: honeypot excluded from a11y tree/tab order and invisible; normal submission still succeeds; existing throttle:10,1 on the route (KOL-139) remains as the rate-limit layer. pint, tsc --noEmit, eslint clean; all 6 LandingPageTest tests pass.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a honeypot field to the public lead contact form to cut down bot spam, on top of the existing throttle:10,1 rate limit (KOL-139). A hidden 'website' input is rendered off-screen and excluded from tab order/screen readers in LeadContactForm (landing.tsx); LeadController silently drops any submission where it's filled, responding exactly like success so bots get no signal to adapt. Verified with a new Pest test (LandingPageTest) plus a live browser check: honeypot is invisible/untabbable and the normal flow is unaffected.
<!-- SECTION:FINAL_SUMMARY:END -->
