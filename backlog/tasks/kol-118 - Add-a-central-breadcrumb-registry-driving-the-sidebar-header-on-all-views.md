---
id: KOL-118
title: Add a central breadcrumb registry driving the sidebar header on all views
status: In Progress
assignee:
  - '@jj'
created_date: '2026-09-18 18:52'
updated_date: '2026-09-18 19:21'
labels: []
dependencies: []
ordinal: 105000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

The sidebar header already has a dedicated breadcrumb slot next to the sidebar toggle, and it renders correctly on the handful of pages that set it. But almost every other page under the main app sidebar (employees, companies, documents, document templates, holidays, leaves, overtime, payroll reports, positions, premises, shifts, workdays, the self-service "my/*" pages, imports, etc.) leaves that slot empty, because each page currently has to opt in individually by hand-declaring its own breadcrumb trail on the page component. The handful of pages that do set it (dashboard, organization settings, roles, the three settings pages) duplicate the same title/href logic that already exists in the sidebar navigation, and a two-level trail (e.g. "Roles > <role name>") has to be hand-built per page with no shared pattern to follow. The result: breadcrumb coverage is inconsistent, most views give the user no trail back to where they came from, and there's no single place to see or audit what the app's breadcrumb structure looks like.

## Solution

Replace the scattered per-page breadcrumb declarations with a single, central breadcrumb registry (the same pattern the popular Laravel `diglactic/laravel-breadcrumbs` package uses, adapted to this app's Inertia/React frontend): one module maps each page to a title and an optional parent page, and the sidebar header resolves the current page's full trail from that map at render time. Every page rendered inside the main app sidebar gets an entry, so the breadcrumb slot is populated everywhere instead of on a handful of pages. Detail/edit/create pages chain to their index page as a parent and can resolve a record-specific trailing title (e.g. the employee's name), giving multi-level trails without per-page trail-building code.

## User Stories

1. As an admin browsing the Employees list, I want to see a breadcrumb trail in the header, so that I have a consistent visual anchor for where I am in the app, same as on the Roles or Settings pages today.
2. As an admin viewing a single employee's detail page, I want the trail to read "Employees > <employee name>", so that I can jump back to the list with one click without using the browser back button.
3. As an admin on an employee's edit page, I want the same "Employees > <employee name>" trail (not a separate "Edit" crumb), so that the trail reflects what I'm looking at, not which form mode I'm in.
4. As an admin creating a new employee, I want the trail to read "Employees > New employee" (localized), so that I still have a one-click way back to the list while I'm mid-creation.
5. As a user on any of the Documents pages (list, create, edit, show), I want the same parent-chained trail pattern as Employees, so that the app feels consistent across sections.
6. As a user on the Document Templates pages, I want the equivalent trail, for the same reason.
7. As a user on the Positions, Premises, Cost Centers, Holidays, Shifts, or Workdays pages, I want breadcrumbs, so that every operational section of the app has the same orientation cue.
8. As an admin on the Overtime section (index, pacts, requests, rest day balances), I want each sub-view to show a trail rooted at "Overtime", so that I know which sub-section I'm in.
9. As an admin on any Payroll Reports page (history, overtime excess, period movements, summary, weekly detail), I want each report to show under a "Payroll reports" root, so that I can tell which report I'm viewing and get back to the others easily.
10. As an employee using a self-service "my/*" page (my documents, my leaves, my workdays, my overtime requests, my overtime rest-day balance), I want a breadcrumb trail too, so that the self-service area feels as complete as the admin area.
11. As an admin walking through the multi-step employee import wizard, I want the trail to root at "Employees > Import" (or similar), so that I know the import is scoped under Employees even while stepping through strategy/mapping/preview/result screens.
12. As a user on the Roles list or a single role's permissions page, I want the same registry-driven trail I'd get today, so that migrating off the old per-page declaration doesn't regress what already works.
13. As a user on the Dashboard, I want the existing "Dashboard" crumb to keep working after the migration, so the entry point of the app is unaffected.
14. As a user on Organization Settings, I want the existing "Organization settings" crumb to keep working after the migration, for the same reason.
15. As a user on any of the three account Settings pages (Profile, Security, Appearance), I want a two-level trail ("Settings > Profile", "Settings > Security", "Settings > Appearance"), so that I know these are grouped under one account-settings area even though they're reached from the user menu rather than the sidebar.
16. As a Spanish-locale user, I want every breadcrumb title translated the same way the rest of the UI is (via the existing translation catalog), so that no page shows an English title while the rest of the app is in Spanish, or vice versa.
17. As a developer adding a brand-new page to the app in the future, I want one obvious place to register its breadcrumb entry, so that I don't have to rediscover the pattern each time or risk shipping a page with an empty breadcrumb slot.
18. As a developer reviewing a PR that adds a page, I want it to be obvious from the diff whether a breadcrumb entry was added, so that missing coverage is caught in review rather than discovered later in the running app.
19. As a user navigating directly to a deep URL (e.g. pasting a link to an employee's edit page, or refreshing the page), I want the breadcrumb trail to render correctly on first load, not just after client-side navigation, so that the trail is reliable regardless of how I arrived at the page.
20. As a user on a DT or SaaS area page, I want my experience to be unaffected by this change, since those areas use their own header bar without a breadcrumb slot and are intentionally out of scope.

## User stories for manual testing (Gherkin)

Scenario: A top-level list page shows a single-level trail
  Given I am signed in as an admin
  When I open the Employees list
  Then the header shows a breadcrumb trail reading "Employees"

Scenario: A detail page shows a two-level trail with the record's name
  Given I am signed in as an admin
  And an employee named "Jane Rivera" exists
  When I open that employee's detail page
  Then the header shows a breadcrumb trail reading "Employees > Jane Rivera"
  And clicking "Employees" navigates me back to the Employees list

Scenario: An edit page shows the same trail as its detail page
  Given I am on Jane Rivera's employee detail page
  When I open her edit page
  Then the header still shows "Employees > Jane Rivera"

Scenario: A create page shows a localized placeholder trailing crumb
  Given I am signed in as an admin with the app in Spanish
  When I open the "new employee" page
  Then the header shows a breadcrumb trail reading "Empleados > Nuevo empleado"

Scenario: Settings pages show a shared "Settings" parent
  Given I am signed in
  When I open Security from the user menu
  Then the header shows a breadcrumb trail reading "Settings > Security"

Scenario: Direct navigation to a deep URL renders the trail on first load
  Given I am signed in as an admin
  When I paste a direct link to an employee's edit page into the address bar and load it
  Then the header shows the correct "Employees > <name>" trail immediately, without needing an extra client-side navigation

Scenario: DT and SaaS areas are unaffected
  Given I am signed in as a DT (labor inspector) user
  When I open any DT page
  Then no breadcrumb slot appears in the header, matching current behavior

## Implementation Decisions

- Introduce a single, central breadcrumb registry module keyed by the Inertia page name (the same string already available as the current page's component identifier, e.g. `"employees/show"`, `"roles/index"`, `"settings/profile"` — this is the app's existing per-page key today, already used to route `Inertia::render()` calls on the backend and already unique per page). Every page rendered inside the main app sidebar gets exactly one entry in this registry.
- Each registry entry declares: a title (either a fixed, translated string, or a function that resolves the title from the current page's own props — needed for record-specific trailing crumbs like an employee's name or a role's name), an href (built from the same Wayfinder route function the page/sidebar nav already uses), and an optional reference to a parent page's registry key.
- A single resolution hook, called once from the sidebar header component, looks up the current page's registry entry and walks the `parent` chain to the root, producing an ordered, root-first list of breadcrumb items. This replaces the sidebar header's current dependency on a `breadcrumbs` prop threaded down through each page's static/callback `layout` declaration.
- Static titles are resolved through the existing translation helper (the same one already used for callback-style layout titles on the Roles show page today), so every registry title is localized the same way as the rest of the UI, driven off the current page's own shared translation props — no new i18n mechanism.
- Remove the existing per-page `layout` breadcrumb declarations (Dashboard, Organization Settings, Roles index/show, and the three Settings pages) and migrate their trails into the new registry as its first entries, to confirm the registry fully replaces the old mechanism before extending it to the rest of the app.
- Extend registry coverage to every remaining page rendered under the main app sidebar: Employees (list/create/edit/show), Companies, Cost Centers, Documents (list/create/edit/show), Document Templates (list/create/edit), Holidays, the Employee Import wizard (all its steps, chained under Employees), Leaves (list/create/calendar), Overtime (index and its pacts/requests/rest-day-balance sub-views), Payroll Reports (each of its report views), Positions (list/show), Premises (list/create/edit), Shifts (list/create/edit), Workdays (list/show), and the self-service "my/*" equivalents of documents, leaves, overtime requests, overtime rest-day balance, and workdays.
- Skip files that live alongside page components but aren't themselves routed Inertia pages (e.g. shared filter/selector sub-components that happen to live in a page directory) — only modules the backend actually targets with `Inertia::render()` get a registry entry.
- Settings pages (Profile, Security, Appearance) get a shared virtual "Settings" parent entry in the registry (title "Settings", href pointing at the Profile page, matching the first item in the settings area's own in-page tab navigation) so their trail reads "Settings > <page>" even though Settings isn't a top-level sidebar nav item.
- Create-page trailing titles use a static localized string (e.g. "New employee") rather than a props-derived one, since there's no record yet at that point.
- Resolution must work correctly on a fresh full-page load (not only after client-side Inertia navigation), since the trail is derived from data already present on the initial page load (the current page name and its own props) rather than anything populated only during client-side transitions.
- Scope is limited to the layout that already owns the breadcrumb slot (the main app sidebar layout, including the nested settings layout, which is wrapped by the same sidebar). The DT and SaaS areas use their own distinct header bars with no breadcrumb slot today and are explicitly out of scope — this ticket does not add one there.

## Testing Decisions

- A good test here observes the actual rendered breadcrumb trail (text and, for non-final crumbs, that they're links to the right destination) rather than asserting internal registry data structures — the registry is an implementation detail; what matters is what ends up in the header.
- Because the trail is fully client-resolved (page name plus page props, both already present on first load), the right seam is a browser-level test that loads a real page and reads the rendered header, not a server-side Inertia assertion (those only see the props sent down, not what the client renders from them). Server-side Feature/Inertia tests remain the right place to confirm a page still ships whatever prop a title resolver depends on (e.g. an employee's name), if that isn't already covered by that page's existing tests.
- Cover a representative sample rather than every single registry entry exhaustively: one top-level list page (single-level trail), one detail/edit pair that shares a parent-chained, record-derived trailing title, one create page (localized static trailing title), and one settings page (shared virtual parent). Exhaustively asserting all ~40+ pages in a browser test suite is low-value relative to its runtime cost; broader coverage can be spot-checked manually during review instead.
- No existing browser-level tests exist in this repo yet to follow as direct prior art; the closest existing pattern is this project's Feature tests that assert on Inertia's `component()`/prop payloads for these same pages (e.g. the Roles and Employees management tests), which should still be used for verifying that a page continues to ship the props a title resolver needs.

## Out of Scope

- Adding a breadcrumb slot to the DT or SaaS header bars — those layouts don't have one today and this ticket doesn't introduce one.
- Any breadcrumb reaction to in-page state that isn't a distinct Inertia page (tab switches, modal/dialog open state, query-string-only filters) — the trail is resolved per page, not per UI state within a page.
- Redesigning the visual appearance of the breadcrumb component itself (spacing, separators, truncation on narrow screens) — this ticket is about coverage and the resolution mechanism, not the existing breadcrumb UI.
- The welcome/marketing page and the auth pages (login, password reset, etc.), which don't use the sidebar layout at all.

## Further Notes

- Given the size of the page inventory (on the order of 40-plus pages), consider splitting delivery into the registry mechanism plus the Dashboard/Organization Settings/Roles/Settings migration as one vertical slice, and the remaining page coverage as one or more follow-up slices grouped by app section (e.g. "Employees + Import wizard", "Documents + Document Templates", "Overtime + Payroll Reports", "the my/* self-service pages") — each independently reviewable and shippable, rather than one large PR touching every page directory at once.
- Registry completeness (i.e., that no page under the main sidebar was missed) is easy to eyeball by diffing the registry's key list against the set of page modules the backend renders — worth a quick manual pass at the end of whichever slice claims to be "the last one," even beyond the representative browser tests.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Every page rendered under the main app sidebar (Employees, Companies, Cost Centers, Documents, Document Templates, Holidays, the Employee Import wizard, Leaves, Overtime and its sub-views, Payroll Reports, Positions, Premises, Shifts, Workdays, and the my/* self-service pages) shows a populated breadcrumb trail in the sidebar header
- [ ] #2 Detail and edit pages for the same record share an identical parent-chained trail with a record-derived trailing title (e.g. Employees > <employee name>)
- [ ] #3 Create pages show a localized static trailing title chained under their index page (e.g. Employees > New employee)
- [ ] #4 Dashboard, Organization Settings, Roles (index and show), and the three account Settings pages keep their current breadcrumb trails after migrating off the old per-page layout declaration onto the new registry
- [x] #5 Settings pages (Profile, Security, Appearance) show a shared Settings parent crumb
- [ ] #6 All breadcrumb titles are localized via the existing translation catalog and render correctly in both configured locales
- [ ] #7 Breadcrumb trails render correctly on a fresh full-page load, not only after client-side navigation
- [ ] #8 The DT and SaaS header bars are unaffected and still have no breadcrumb slot
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
Slice 1 (this pass): breadcrumb registry mechanism + migrate the pages that already declared breadcrumbs (Dashboard, Organization Settings, Roles index/show, Settings Profile/Security/Appearance) off the old per-page `.layout` declarations.

1. Add resources/js/lib/breadcrumbs.ts: a registry keyed by Inertia page name (component string), entry = { title: translation-key | (props) => string, href: Wayfinder route, parent?: registry key }. Includes a virtual `settings` entry (no real page) so the three Settings pages share a "Settings" parent.
2. Add resources/js/hooks/use-breadcrumbs.ts: reads usePage().component + props, walks the registry's parent chain root-first, resolves string titles via the existing translate() helper. Missing registry entry -> empty trail (matches current default-empty behavior for unmigrated pages).
3. AppSidebarHeader calls useBreadcrumbs() itself instead of receiving a breadcrumbs prop; drop the prop from AppSidebarLayout and AdminLayout (and the unused starter-kit app-layout.tsx, kept typechecking).
4. Remove Dashboard.layout / OrganizationSettings.layout / RolesIndex.layout / RolesShow.layout / Profile.layout / Security.layout / Appearance.layout and their now-unused imports; add matching registry entries. Reused the sidebar nav's own translation keys (ui.nav.*) instead of the old hand-duplicated/hardcoded strings, fixing the Organization Settings crumb ("Organization settings" -> "General settings", now consistent with the sidebar nav item) and the Security crumb (was an untranslated hardcoded string).
5. Settings pages: use ui.settings.nav.profile/security/appearance (short labels) chained under the new virtual "settings" entry, per the ticket's "Settings > Profile" Gherkin scenario - a deliberate change from the old single-level "Profile settings" crumb.

Remaining sections (Employees+Import wizard, Documents+Templates, Positions/Premises/Cost Centers/Holidays/Shifts/Workdays, Overtime+sub-views, Payroll Reports, the my/* self-service pages) are out of scope for this pass - see follow-up tasks.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified in browser (dev server, Chrome DevTools MCP) on both locales:
- /dashboard -> "Dashboard"/"Panel", link resolves to itself
- /roles -> "Roles"; /roles/2 -> "Roles > Permissions" (Roles links back), matches prior text exactly
- /organization-settings -> "General settings" (was "Organization settings"; now matches sidebar nav label - deliberate fix, see plan)
- /settings/profile, /settings/appearance -> "Settings > Profile" / "Settings > Appearance" in both locales (Settings links to Profile)
- /employees (unmigrated page) -> no breadcrumb nav rendered, same as before (no regression)
- DT/SaaS layouts untouched, no breadcrumb slot

Automated: npm run types:check clean (2 pre-existing unrelated errors in roles/index.tsx and roles/show.tsx confirmed via git stash, not touched by this change); eslint clean; prettier clean; sail artisan test --compact --filter="DashboardTest|RoleManagementTest|RolesPermissionsTest|OrganizationSettingsTest|ProfileUpdateTest|SecurityTest" passed (66 passed, 3 skipped, 334 assertions). No PHP files changed, so no new Pest test required.

User declined installing pestphp/pest-plugin-browser for this slice (not in the repo yet, dependency change needs approval) - verified the rendered trail manually via Chrome DevTools MCP instead of adding a browser-level Pest test.

Code review (angle A) flagged AC #4 as incorrectly checked: it says the migrated pages "keep their current breadcrumb trails," but Organization Settings' text changed ("Organization settings" -> "General settings", now matching the sidebar nav label) and the three Settings pages changed shape (single crumb -> "Settings > <page>"). That's intentional and matches the ticket's own Implementation Decisions/User Story #15/Gherkin ("Settings > Security" etc.), not a regression - but it contradicts AC #4's literal wording, so I'm leaving it unchecked pending a sign-off from the user/ticket owner that the wording should be read as "keeps working," not "byte-identical text." Dashboard and Roles (index/show) text did stay identical.

Also applied from the same review: typed the registry's function-title props via Inertia's own Page['props'] type instead of Record<string, unknown>; imported BreadcrumbRegistryEntry in the hook instead of re-deriving it structurally; added a visited-keys guard so a misconfigured parent cycle can't hang the render loop; added a dev-only console.warn when a `parent` key doesn't resolve (a real page being unmigrated is expected and silent, a dangling parent reference is always a typo); switched to useTranslations().t() instead of calling translate() directly, matching the rest of the codebase. Not applied: deduplicating title/href against app-sidebar.tsx's nav declarations (the registry is deliberately a separate concept per the ticket - e.g. Roles show's "Permissions" crumb has no nav equivalent) and adding a JS/TS test (no test runner is configured in this repo; adding one is a dependency change, same category the user already declined for pest-plugin-browser in this slice).
<!-- SECTION:NOTES:END -->
