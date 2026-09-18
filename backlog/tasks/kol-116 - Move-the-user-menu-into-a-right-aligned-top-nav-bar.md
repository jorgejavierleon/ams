---
id: KOL-116
title: Move the user menu into a right-aligned top nav bar
status: Done
assignee:
  - '@jorgejavierleon@gmail.com'
created_date: '2026-09-18 14:51'
updated_date: '2026-09-18 17:37'
labels: []
milestone: m-4
dependencies: []
references:
  - /home/jj/.claude/image-cache/86e7ec13-1f73-48f4-81a3-b23a172946b1/11.png
ordinal: 103000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Today the sidebar layout's header (resources/js/components/app-sidebar-header.tsx) only renders the sidebar trigger and breadcrumbs — there is no right-aligned action area. The user dropdown (avatar, name, profile/logout links) lives instead in the sidebar footer via NavUser (resources/js/components/nav-user.tsx), rendered inside AppSidebar's SidebarFooter (resources/js/components/app-sidebar.tsx). The user wants the top nav extended with a right-aligned section (inspired by a reference screenshot showing icon buttons ending in a user avatar dropdown, top-right), and the user menu relocated there instead of sitting in the sidebar.

Note: the app already has an alternate header-only layout (resources/js/components/app-header.tsx, used by app-header-layout.tsx) with a working right-aligned cluster (search icon, external links, user avatar dropdown via the same UserMenuContent). That is not the layout in active use — the default app shell uses the sidebar layout (app-sidebar-layout.tsx -> AppSidebar + AppSidebarHeader). This ticket is about adding a right-aligned section to AppSidebarHeader (the slim top bar used with the sidebar) and moving the user dropdown there, not about switching layouts.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The sidebar layout's top bar (AppSidebarHeader) has a right-aligned section, matching the sidebar trigger + breadcrumbs on the left
- [x] #2 The user avatar/name dropdown (profile link, log out) is rendered in that right-aligned top bar section instead of the sidebar footer
- [x] #3 NavUser is removed from the sidebar footer once the top bar renders the user menu, so it does not appear in both places
- [x] #4 The relocated user menu keeps its existing dropdown content (profile link and log out) and behavior unchanged
- [x] #5 Layout remains usable and the right-aligned section does not overlap breadcrumbs on narrow viewports
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Rewrite resources/js/components/nav-user.tsx to render an avatar-only DropdownMenu trigger (Button+Avatar, matching app-header.tsx's pattern) with UserMenuContent as its dropdown content, dropping the SidebarMenuButton/useSidebar/isMobile logic no longer needed outside the sidebar footer.
2. Update resources/js/components/app-sidebar-header.tsx to add a right-aligned, shrink-0 section rendering <NavUser />, using justify-between + min-w-0 on the left group so breadcrumbs wrap instead of overlapping the avatar on narrow viewports.
3. Remove <NavUser /> and its import from resources/js/components/app-sidebar.tsx's SidebarFooter (drop the now-empty SidebarFooter usage).
4. Run npm run types:check and npm run lint:check; no PHP touched so no Pest test is required, verify manually via a browser check that the dropdown still shows profile link + logout and behaves the same.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Rewrote nav-user.tsx into an avatar-only DropdownMenu trigger (Button+Avatar, matching app-header.tsx's existing pattern) reused by the new right-aligned section of AppSidebarHeader; removed NavUser/SidebarFooter from app-sidebar.tsx. Added justify-between + min-w-0 (left) / shrink-0 (right) so breadcrumbs wrap under the flex-wrap BreadcrumbList instead of overlapping the avatar. Code review (subagent) flagged the avatar-only trigger lost its accessible name vs. the old name-bearing SidebarMenuButton -- fixed by adding aria-label={t('ui.user_menu.open')} (new lang key in en/es ui.php, 'User menu'/'Menú de usuario'). Verified in Chrome (dashboard + narrow-breadcrumb pages): dropdown opens with Configuración/Idioma/Cerrar sesión unchanged, a11y snapshot now shows button labelled 'Menú de usuario'. Ran full suite twice (before and after the aria-label fix): sail artisan test --compact -> 1441 passed, 4 skipped, 0 failed. pint --dirty --format agent -> passed. types:check -> only pre-existing unrelated errors in roles/index.tsx and roles/show.tsx (present on master before this change). No new Pest test added for the two-line lang-file translation key addition (no new logic, aria-label string only) -- consistent with prior guidance to skip tests for trivial display-only PHP changes; flagging this explicitly rather than checking DoD #4.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Moved the user avatar dropdown from the sidebar footer (NavUser inside AppSidebar's SidebarFooter) into a new right-aligned section of AppSidebarHeader, matching the reference screenshot's slim top-bar pattern. nav-user.tsx now renders an avatar-only trigger (Button+Avatar+UserMenuContent, mirroring app-header.tsx's existing unused-layout pattern) instead of the old SidebarMenuButton; NavUser and SidebarFooter were removed from app-sidebar.tsx. Added an aria-label (new ui.user_menu.open translation, en+es) after code review flagged the avatar-only trigger had no accessible name. Verified: full Pest suite green (1441 passed/4 skipped), pint clean, types:check clean of new issues, and manual browser check (Chrome DevTools MCP) on /dashboard and /employees/*/edit confirming the dropdown (Configuración, Idioma, Cerrar sesión) works unchanged from its new location and doesn't overlap breadcrumbs.
<!-- SECTION:FINAL_SUMMARY:END -->
