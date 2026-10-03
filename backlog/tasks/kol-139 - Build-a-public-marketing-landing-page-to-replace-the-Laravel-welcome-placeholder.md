---
id: KOL-139
title: >-
  Build a public marketing landing page to replace the Laravel welcome
  placeholder
status: Done
assignee: []
created_date: '2026-10-03 19:24'
updated_date: '2026-10-03 20:18'
labels:
  - frontend
  - marketing
dependencies: []
references:
  - >-
    /home/jj/Documents/kolvi/Kolvi landing page
    design-handoff/kolvi-landing-page-design/project/Kolvi Landing.dc.html
  - /home/jj/Documents/kolvi/logos/brand
priority: medium
type: feature
ordinal: 154000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The `/` route (routes/web.php) currently renders `resources/js/pages/welcome.tsx`, the stock Laravel/Inertia starter page — not real marketing content. Replace it with an actual Kolvi landing page.

**Design reference (style/UX only, not content):** a Claude Design mockup was exported as a handoff bundle on this machine at `/home/jj/Documents/kolvi/Kolvi landing page design-handoff/kolvi-landing-page-design/project/Kolvi Landing.dc.html` (brand source files under `/home/jj/Documents/kolvi/logos/brand/`). It lays out: a sticky dark header (logo, anchor nav, Ingresar, Agendar demo CTA) → hero (eyebrow, H1, subcopy, two CTAs, a live-looking compliance dashboard card) → a 3-stat facts strip → three alternating feature rows → a "cómo funciona" 3-step section → a pricing grid → an FAQ accordion → a demo-request CTA band with an email field → footer. Use it for section rhythm, spacing, and type scale (Sora for headings, Plus Jakarta Sans for body) only. Do not reuse its color tokens (`--color-ink`, `--color-primary`, `--color-accent-coral`, etc. are throwaway tokens scoped to that prototype) — map everything to this app's real theme in `resources/css/app.css`. Do not reuse its raster `kolvi-icon-white.png`; the app already has brand components (`resources/js/components/app-logo.tsx`, `app-logo-icon.tsx`, `app-logo-mark.tsx`) from KOL-138.

**Content must change** — the mock is generic time-clock-SaaS copy with invented numbers. Rewrite every section (hero claim, facts strip, the 3 feature rows, steps, FAQ) around what this app actually does:
- Attendance: web/mobile clock-in-out, geofenced premises, one-mark-per-day guard, signed receipt with folio (Res. 38 Art. 13)
- Overtime: pactos, the 44h→40h legal limit phase-down, authorization workflow, rest-day compensation or payment
- Leave requests: self-service with supervisor/admin approval
- Payroll reports: Maestro de Trabajadores, Resumen de Remuneraciones, Movimientos del Periodo, Detalle Semanal, Excesos de Jornada y HHEE — DT-ready exports
- Document templates with e-signature
- Employee bulk import
- Roles & permissions (Owner/Admin/Supervisor/Employee), multi-premise/shift support

**Must add a feature the mock has no equivalent for:** the MCP server (`app/Mcp/Servers/KolviServer.php`, KOL-126 through KOL-131, KOL-136) lets an organization manage leave, overtime, employees, document templates and payroll reports through an AI assistant instead of only the web UI. The user wants this called out as a headline differentiator versus competitors, not a footnote — give it its own prominent feature slot (e.g. a 4th feature row or a featured callout near the top).

No real product screenshots exist yet for the hero/feature visuals — build them from the app's actual UI vocabulary (existing shadcn components, DataTable, stat/badge patterns already in the app) rather than inventing new mock numbers.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The '/' route renders a new landing page component instead of welcome.tsx; authenticated app routes are unaffected
- [x] #2 Section layout (header/nav, hero, facts strip, feature rows, cómo-funciona steps, FAQ accordion, demo CTA, footer) follows the mock's rhythm, built with the app's real tailwind theme tokens, not the mock's standalone --color-* variables
- [x] #3 All visible copy is in Spanish and describes only features that exist in the app today (attendance, overtime, leave, payroll reports/DT export, document e-signature, employee import, roles)
- [x] #4 A dedicated, prominent feature block advertises managing the app via AI through the MCP server as a key differentiator
- [x] #5 Logo uses the existing app-logo components, not a new raster asset
- [x] #6 'Ingresar' links to the real login route; every other link either scrolls to an in-page section or is a real route
- [x] #7 Pricing section is resolved with the product owner before shipping real numbers — the app has no billing/plan model today, so default to a 'Hablar con ventas' contact CTA unless told otherwise
- [x] #8 The demo-request email form submits to a real backend endpoint that records the lead, instead of only simulating success client-side like the mock
- [x] #9 Page is responsive down to mobile width and keyboard/screen-reader accessible (heading order, accordion button semantics, labeled email input)
- [x] #10 A feature test covers the landing route rendering and the lead-capture endpoint persisting a submission
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
1. Migration+model+controller+route for demo-request lead capture (POST /demo-requests), validated email, Pest feature test.
2. New resources/js/pages/landing.tsx replacing welcome.tsx: dark sticky header (AppLogo, anchor nav, Ingresar->login(), Agendar demo CTA), hero w/ compliance-dashboard visual, facts strip, 4 feature rows (marcación geocercada, horas extra/pactos, reportes DT, MCP/IA differentiator), cómo-funciona steps, pricing->'Hablar con ventas' contact block (no invented prices per AC7), FAQ accordion (Radix Collapsible, no new dep), demo-request CTA band wired to backend, footer.
3. Wire routes/web.php: '/' -> landing page, update app.tsx layout switch (welcome->landing, null layout). Delete welcome.tsx.
4. Run npm build for wayfinder actions, Pint, Pest, types:check.
5. Browser-verify responsive/dark-mode/accessibility before Phase 4 gate.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified: LandingPageTest (3 tests) covers GET / rendering + POST /demo-requests persistence/validation. Browser-verified desktop (1280px) and mobile (390px) layouts, FAQ accordion aria-expanded toggling, and an end-to-end demo-request submission that persisted a DemoRequest row (confirmed via tinker, then cleaned up). Pint clean, npm run types:check clean, eslint clean, npm run build succeeds and production CSS contains all utility classes used. Pricing section intentionally carries no real numbers (Hablar con ventas CTA only, per AC7).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Replaced the Laravel welcome placeholder with a real Kolvi marketing landing page at '/': sticky header, hero with a compliance-dashboard visual, facts strip, 4 feature rows (geofenced attendance, overtime/pactos, DT-ready reports, and a prominently highlighted MCP/AI differentiator), cómo-funciona steps, a pricing section that defers to a 'Hablar con ventas' CTA (no invented numbers), an FAQ accordion (built on the existing Radix Collapsible, no new dependency), and a demo-request form that persists leads via a new DemoRequest model/migration/controller. Verified with a new LandingPageTest (route rendering + lead capture + validation) and manual browser testing (desktop/mobile layout, accessibility tree, live form submission).
<!-- SECTION:FINAL_SUMMARY:END -->
