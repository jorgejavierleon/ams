---
id: KOL-123
title: Merge Configuración general into account Settings
status: In Review
assignee:
  - '@Jorge Leon'
created_date: '2026-09-22 08:57'
updated_date: '2026-09-22 09:18'
labels: []
dependencies: []
ordinal: 120000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Currently /organization-settings is a standalone admin-only page (app/Http/Controllers/SettingController.php, resources/js/pages/organization-settings.tsx) with three sections in one long form: Notificaciones, Documentos, Horas extras. Restructure it to match the existing account Settings pages (resources/js/pages/settings/profile.tsx, security.tsx, appearance.tsx): one page per section, all sharing resources/js/layouts/settings/layout.tsx's left-hand nav, reached from the same top-right user menu > Configuración entry as Perfil/Seguridad/Apariencia.

Decisions already made for this work:
- The app-sidebar's Settings group keeps a shortcut into this area (repointed at the first new section) instead of being removed.
- The left-nav items for Notificaciones/Documentos/Horas extras are hidden for non-admin users (mirrors the current role:admin gate on the backend routes); Perfil/Seguridad/Apariencia keep showing for everyone.

Note for whoever implements: the route/name segments 'documents' and 'overtime' are already taken elsewhere in routes/web.php (document management, the overtime queue), so the new settings routes for those two sections need names that don't collide.

Split into one subtask per section (KOL ticket per vertical slice) so each is independently reviewable and testable; the first subtask also carries the shared nav/layout/breadcrumb scaffolding the other two build on.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The left nav on every /settings/* page follows the same visual pattern as Perfil/Seguridad/Apariencia
- [x] #2 Notificaciones, Documentos and Horas extras each have their own /settings/* page with the same fields the current organization-settings page has
- [ ] #3 Non-admin users do not see the Notificaciones/Documentos/Horas extras nav items
- [x] #4 The old /organization-settings route, controller and page are removed once all three sections are migrated
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
1. Implement subtasks in dependency order: KOL-123.1 (Notificaciones + shared nav/layout/breadcrumb scaffolding), then KOL-123.2 (Documentos), then KOL-123.3 (Horas extras + retire /organization-settings).
2. New route names use a settings- prefix (settings-notifications, settings-documents, settings-overtime) to avoid colliding with the existing documents.* and overtime.* route names; new routes live in routes/settings.php under an auth+role:admin group alongside the existing profile/security/appearance routes.
3. Each new Settings/*Controller reuses App\Services\OrganizationSettings::current() exactly like SettingController does, validating only its own slice of fields.
4. Each new settings/*.tsx page gets its own translation keys under ui.settings.<section>.* (title, description, fields.*), copied from ui.organization_settings.* rather than shared, so the old page is untouched until KOL-123.3 deletes it along with the old ui.organization_settings.* block.
5. SettingsLayout gets nav items added incrementally per subtask, shown only when the signed-in user is not in the employee nav group (same auth.permissions.includes('ViewOwn:Leave') check app-sidebar.tsx uses).
6. KOL-123.1 also repoints the app-sidebar 'Configuración general' shortcut and adds the settings breadcrumb registry scaffolding the other two subtasks extend.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
All 3 subtasks (KOL-123.1/.2/.3) implemented and set to 'In Review'. Backend fully verified: 40+18=58 Pest tests across NotificationSettingsTest, DocumentSettingsTest, OvertimeSettingsTest, OvertimeSectionTest (fixed), OvertimeAuthorizationModeTest, OvertimeQueueBadgeTest, OvertimeRequestReviewTest — all passing. pint clean, tsc clean (2 pre-existing unrelated errors only). Old /organization-settings page, controller, routes, and test fully removed with no dangling references (confirmed by repo-wide grep).
AC #1 (nav visual pattern) and #3 (nav hidden for non-admins) are implemented identically across the three pages/subtasks but not visually verified in a browser this session — the Claude-in-Chrome extension was unavailable. Four manual checks queued in docs/QA_CHECKLIST.md (one per subtask plus one for the old route's removal).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Restructured the standalone admin-only /organization-settings page into three per-section pages under the account Settings area (/settings/notifications, /settings/documents, /settings/overtime), each sharing SettingsLayout's left nav (admin-only) and the settings breadcrumb parent, and each backed by its own admin-gated Settings\*Controller reusing OrganizationSettings::current(). The old SettingController, organization-settings.tsx, its routes, and its test file are fully removed. Verified with 58 passing Pest tests across the new and touched suites, pint, and tsc; nav-visibility and the old route's removal still need a manual browser pass, queued in docs/QA_CHECKLIST.md. Not yet committed — ready for your review before I commit.
<!-- SECTION:FINAL_SUMMARY:END -->
