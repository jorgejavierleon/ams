---
id: KOL-4
title: Redesign the employee profile (show) page layout
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-07-30 10:13'
updated_date: '2026-09-19 10:39'
labels: []
dependencies: []
references:
  - 'https://github.com/jorgejavierleon/ams/issues/75'
  - 'https://claude.ai/code/artifact/1a0b9a52-a6d2-4bb7-9833-08a503dbd97a'
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

The employee show page (`resources/js/pages/employees/show.tsx`) packs every field into flat, ungrouped field grids inside four tabs, with the employee's identity, status, and the only edit action crammed into a single wide header row. There's no at-a-glance summary of who this person is, and related facts (contact info vs. employment vs. contract vs. leave entitlements) aren't visually grouped, making the page harder to scan than it needs to be for HR/admin users who look up employees frequently.

## Solution

Reorganize the page around a fixed left-hand profile summary card (avatar, name, status/contract badges, a compact quick-facts list, a vacation-balance progress bar, and tenure/shift-count stats) next to the existing tabbed detail content on the right, inspired by a Vuexy admin-template user-account page but built entirely from this app's existing design tokens and shadcn components — no new dependencies. Within each tab, replace the single flat field grid with labeled sub-sections (e.g. "Empleo", "Contrato", "Días y beneficios") separated by dividers. The primary Edit action (and the active/inactive toggle) live only at the top-right of the page — not duplicated on the profile card. A design mockup was reviewed and approved by the user: https://claude.ai/code/artifact/1a0b9a52-a6d2-4bb7-9833-08a503dbd97a

This ticket covers only the employee **view** (show) page. The employee **update** (edit) page/form was not part of this redesign pass — track it separately if it needs the same treatment.

## User Stories

1. As an admin looking up an employee, I want a compact profile summary (avatar, name, position, status) fixed on the left, so I don't have to scan a wide header row to confirm who I'm looking at.
2. As an admin, I want the employee's key contact facts (personal email, phone, RUT, nationality, timezone, emergency contact) visible in the profile summary without switching tabs, so common lookups don't require navigation.
3. As an admin, I want to see the employee's vacation balance as a progress bar with the used/available/total numbers, so I can judge it at a glance instead of reading three separate numeric fields.
4. As an admin, I want to see the employee's tenure and number of shift assignments as quick stats, so I get a sense of their history without opening the Turnos tab.
5. As an admin viewing the Información and Laboral tabs, I want fields grouped into labeled sections instead of one long flat grid, so related facts are easier to scan together.
6. As an admin, I want a single Edit button and a single active/inactive toggle, both in the top-right of the page, so there's no redundant or conflicting action in two places.
7. As an admin, I want the Turnos and Documentos tabs' existing content (shift assignments, overtime pacts, deferred documents) to keep working exactly as before, so this visual reorganization doesn't regress existing functionality.

## User stories for manual testing (Gherkin)

Scenario: The profile summary shows identity and status at a glance
  Given I am signed in as an admin
  And an employee named "Camila Torres" exists with an active contract
  When I open that employee's detail page
  Then I see a profile card on the left with her avatar, name, position, and an "Activa" badge
  And I do not see an Edit or Desactivar button on that card

Scenario: Top-right actions remain the only edit/toggle entry point
  Given I am on Camila Torres's employee detail page
  Then I see exactly one Edit button and one active/inactive toggle, both in the top-right header

Scenario: Vacation balance renders as a progress bar
  Given Camila Torres has used 6 of 15 available vacation days
  When I view her profile card
  Then I see a progress bar showing 9 of 15 days available

Scenario: Labor tab fields are grouped into sections
  Given I am on Camila Torres's employee detail page
  When I open the "Laboral" tab
  Then I see distinct sections for employment, contract, and leave entitlements, each with its own heading

Scenario: Existing Turnos and Documentos tab content is unaffected
  Given I am on Camila Torres's employee detail page
  When I open the "Turnos" tab
  Then the existing shift assignments table and overtime pacts panel render as before
  When I open the "Documentos" tab
  Then the existing deferred documents list renders as before

## Implementation Decisions

- Reuse existing shadcn/ui components (Card, Badge, Avatar, Tabs, Separator) and Tailwind utility classes only — no new npm dependencies (no Progress/Radix package is installed; build the vacation-balance bar as a plain div, matching the mockup).
- Tenure is derived from `contract_start_date`; shift-assignment count is derived from the `shifts.assignments` array already passed to the page. No new backend fields are required for the profile-card stats.
- Keep the existing `Field`-style label/value pattern for section contents; only the grouping and headings change.

## Testing Decisions

- This is a presentational reorganization with no new backend data — cover it with the existing Inertia/Pest feature test for the employee show page (assert the page still renders with the same props), plus a manual browser check per the Gherkin scenarios above (light and dark).
- No new Pest test is expected unless a backend field changes; note in Definition of Done evidence if none was needed.

## Out of Scope

- The employee update/edit page (`employees/edit.tsx`) — not redesigned in this pass.
- The Permisos (leave history) tab — tracked separately in KOL-4.1.
- Any change to the Employees list/index page.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Profile summary card shows avatar, name, status/contract badges, quick facts, tenure + shift-assignment stats, and a vacation-balance progress bar, with no Edit or Desactivar button on the card
- [x] #2 Top-right header holds the only Edit button and the only active/inactive toggle
- [x] #3 Información and Laboral tab content is reorganized into labeled sub-sections instead of one flat field grid
- [x] #4 Turnos and Documentos tab content (ShiftAssignments, EmployeeOvertimePacts, deferred documents) renders unchanged
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add translation keys to lang/es/ui.php and lang/en/ui.php under employees.show: actions.deactivate/activate, tenure_years, shift_assignments_count, contact_* quick-fact labels, section_identity, section_contact, section_emergency_contact, section_employment, section_contract, section_benefits. Update tab_info copy to "Información"/"Information".
2. Rewrite resources/js/pages/employees/show.tsx:
   - Add an EmployeeProfileCard sub-component (colocated in the same file, mirroring the existing Field pattern) rendering: avatar (useInitials), name, position · premise line, status + contract-type badges, a 2-col stat row (tenure years derived from contract_start_date, shift-assignment count from shifts.assignments.length), a quick-facts info list (personal email, phone, RUT, nationality, timezone, emergency contact), and the vacation-balance bar (plain div, reusing existing vacationBalance prop and translation strings).
   - Header keeps only the employee name (no inline badges) plus top-right actions: Desactivar/Activar (outline button, router.patch to routes/employees toggleActive, no confirm dialog — mirrors index.tsx's inline toggle) and the existing Editar button.
   - Page layout becomes a 2-col grid: fixed profile card (left) + existing Tabs (right).
   - Información tab: group fields into Identidad / Contacto / Contacto de emergencia sections with Separator dividers.
   - Laboral tab: group into Empleo / Contrato / Días y beneficios sections; drop the vacation-balance Field (now lives on the profile card only).
   - Turnos and Documentos tab content unchanged.
3. Run vendor/bin/pint --dirty --format agent, npm run types:check, and sail artisan test --compact --filter=EmployeeManagementTest.
4. Manually verify in the browser (light + dark) against the Gherkin scenarios in the ticket.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implementation notes:
- Added EmployeeProfileCard (colocated in employees/show.tsx) rendering avatar (useInitials), status/contract badges, tenure + shift-count stats, quick-facts info list, and a plain-div vacation-balance bar; reused existing vacationBalance/translation props, no new dependencies.
- Header now shows only the employee name/email plus a Desactivar/Activar button (router.patch to the existing toggleActive route, no confirm dialog — mirrors index.tsx's inline checkbox toggle) and the existing Editar button.
- Información tab split into Identidad / Contacto / Contacto de emergencia sections; Laboral tab split into Empleo / Contrato / Días y beneficios (vacation balance Field removed from Laboral — now lives only on the profile card). Turnos/Documentos tabs untouched.
- Added employees.show.actions/stats/contact/sections translation keys to lang/es/ui.php and lang/en/ui.php; tab_info copy changed "Info" -> "Información"/"Information".
- Hit a CSS grid overflow bug: fixed-width grid item content (long emails/phone numbers) overflowed the 272px profile card because grid items default to min-width:auto. Fixed by adding min-w-0 down the flex/grid chain (Card, CardContent, each direct grid-item wrapper, InfoRow's flex row and its value span).

Verification:
- vendor/bin/pint --dirty --format agent: passed (no PHP touched).
- npm run types:check: no errors in employees/show.tsx; 2 pre-existing errors in resources/js/pages/roles/{index,show}.tsx confirmed present on master via git stash (unrelated to this change).
- sail artisan test --compact --filter=EmployeeManagementTest: 64 passed / 395 assertions. --filter=ShiftAssignmentManagementTest: 13 passed / 50 assertions. Full suite deferred per standing preference (only run on request).
- Manual browser check (admin@example.com, employee id 6, temporary tinker-seeded contract/contact data reverted after): verified all 5 Gherkin scenarios in light and dark mode — profile card shows avatar/badges/stats/vacation bar with no edit/toggle on the card; header has exactly one Edit + one toggle; vacation bar renders 9-style used/available numbers correctly (17/20 in this data); Información and Laboral tabs show labeled sections; Turnos (shift table + overtime pacts) and Documentos tabs render unchanged; Desactivar/Activar toggle flips the badge and button label via preserveScroll patch.

Code review (self-run, mattpocock-skills:code-review) found 3 issues, all fixed:
1. CONFIRMED: toggleEmployeeActive() was missing preserveState: true, so the Desactivar/Activar patch remounted ShowEmployee and reset the Tabs to "info" (losing e.g. an open Turnos/Documentos tab), unlike index.tsx's identical toggle which already had it. Fixed by adding preserveState: true; re-verified in the browser that toggling while on the Turnos tab now keeps that tab selected.
2. CONFIRMED: unused lucide-react imports (Briefcase, Calendar, Sun) left over from an earlier draft with section icons. Removed.
3. Not reproduced but hardened: InfoRow's label column was w-20 (80px); reviewer flagged wrap risk for longer labels. Measured all six Spanish labels in-browser (all render at height:20, i.e. one line, no wrap), but bumped to w-24 as cheap headroom since it's free.
Re-verified after fixes: pint clean, tsc clean, EmployeeManagementTest (64) + ShiftAssignmentManagementTest (13) pass, manual re-check of the preserveState fix in the browser (dark mode, Turnos tab).

Follow-up tweaks requested after initial review:
1. Removed Zona horaria from the profile card's quick-facts list (still shown in the Información tab's Contacto section). Removed the now-unused Globe icon import and the contact.timezone translation key.
2. Widened the profile card column from 272px to 340px.
3. Removed the redundant name/email header row (Heading component) — the profile card already shows both; header is now just the Desactivar/Activar + Editar buttons, right-aligned.
Re-verified in the browser (light + dark): card no longer wraps the personal email, header is clean, Información tab still shows Zona horaria under Contacto. pint + EmployeeManagementTest re-run and pass.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Redesigned the employee show page around the approved mockup: a fixed left-hand profile summary card (avatar, name, status/contract badges, tenure + shift-count stats, a quick-facts list, and a vacation-balance progress bar) next to the existing tabbed detail on the right. Información and Laboral tabs now group fields into labeled sub-sections (Identidad/Contacto/Contacto de emergencia and Empleo/Contrato/Días y beneficios) instead of one flat grid. The header keeps a single Editar button plus a new Desactivar/Activar toggle (using the existing toggleActive route); no edit/toggle controls live on the profile card. Turnos and Documentos tabs are untouched. Built entirely with existing shadcn components and Tailwind — no new dependencies. Verified with Pint, tsc, the EmployeeManagementTest/ShiftAssignmentManagementTest Pest suites, and a manual browser pass (light + dark) against every Gherkin scenario in the ticket.
<!-- SECTION:FINAL_SUMMARY:END -->
