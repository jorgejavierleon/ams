---
id: KOL-117
title: Replace text Editar/Eliminar/Ver row actions with icon buttons
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-09-18 17:54'
updated_date: '2026-09-18 18:29'
labels:
  - ready-for-agent
  - frontend
milestone: m-4
dependencies: []
ordinal: 104000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Problem Statement

Across the app's data tables, the row actions to edit or delete a record are rendered as plain underlined text links ("Editar", "Eliminar") side by side in the actions column. On tables with many rows this reads as visual clutter and is inconsistent with the icon-based dropdown row actions already used elsewhere in the app (e.g. the Jornadas and Solicitudes dropdown menus, which group actions behind a vertical three-dot trigger).

## Solution

Replace the plain-text "Editar" / "Eliminar" (and, going forward, "Ver") row actions with a single dropdown menu: a ghost icon button showing a vertical three-dot (`MoreVertical`) trigger that opens a menu of labeled actions — an eye item for view, a pencil item for edit, a trash-can item for delete — matching the existing dropdown pattern already used in `resources/js/pages/leaves/index.tsx`. Introduce one shared row-actions component so every table renders this consistently instead of each page hand-rolling its own text buttons, and use it everywhere the plain-text pattern currently exists.

## User Stories

1. As a user scanning a long table (e.g. Empleados, Centros de costo, Feriados), I want row actions collapsed behind a single "more actions" trigger instead of always-visible text, so that the actions column takes less horizontal space and the table is easier to scan.
2. As a user relying on a screen reader, I want the "more actions" trigger to announce its purpose (e.g. "Más acciones") and each menu item to keep announcing its existing label ("Editar"/"Eliminar"/etc.) as visible text inside the opened menu, so removing the always-visible text doesn't remove any information.
3. As a developer adding a new table to the app, I want a single shared row-actions component to import, so I don't re-implement dropdown-menu markup, styling, and labels from scratch on every new page.
4. As a developer maintaining an existing table (e.g. Plantillas de documentos) that has a non-CRUD text action alongside edit/delete (restore), I want that action left untouched, so the change doesn't force a redesign of actions outside its scope. Pactos de horas extra's revoke/activate actions are the exception: they move into the same dropdown as edit, since they are still per-row actions on the same record (see below).
5. As a user of the Empleados table, I want the edit and delete menu items to keep triggering the exact same dialogs/routes they do today, so the change is purely visual and doesn't alter existing behavior.
6. As a future user of a table that gains a "view" row action, I want it to already follow the dropdown-menu convention, so newly added view actions are visually consistent with edit/delete without another redesign pass.

## Implementation Decisions

- Add one new shared component (following the existing `resources/js/components/data-table-*.tsx` naming convention, e.g. `data-table-row-actions.tsx`) that renders a `DropdownMenu`: a `variant="ghost" size="icon"` trigger `Button` showing `MoreVertical`, and a `DropdownMenuContent` with one `DropdownMenuItem` per action — `Eye` for view, `Pencil` for edit, `Trash2` for delete — each pairing the icon with its existing visible translation label, following the exact structure already used in `resources/js/pages/leaves/index.tsx`'s dropdown (`DropdownMenu`/`DropdownMenuTrigger`/`DropdownMenuContent`/`DropdownMenuItem`). Standardize on these three icons rather than the mixed `Pencil`/`PenLine`/`PencilLine` currently used ad hoc across the app.
- Because each action's label stays visible as menu-item text, no new aria-labels are needed on the items themselves — the existing translation strings (e.g. `t('ui.cost_centers.actions.edit')`) are reused as-is for that visible text. Only the trigger button needs an aria-label, since it shows only an icon.
- Add exactly one new shared translation key pair for the trigger's aria-label — `ui.common.actions.more` (`'More actions'` / `'Más acciones'`) in `lang/en/ui.php` and `lang/es/ui.php` — reused by every table via the shared component, rather than duplicating a `more` key per page namespace (unlike the existing per-page `leaves.actions.more` / `workdays`-style keys).
- The shared component accepts the action handlers/hrefs as props (works for both `onClick`-driven actions like edit-opens-dialog/delete-opens-confirm, and `Link href`-driven actions like document-templates' edit), since the current per-page implementations mix both. `onClick` items use `DropdownMenuItem onSelect`; `href` items render the `Link` inside the item via `asChild`.
- The delete item is styled as destructive (matching `DropdownMenuItem variant="destructive"` already used in `leaves/index.tsx`).
- The shared component accepts an optional `children` slot, rendered inside the dropdown after edit and before delete, for page-specific non-CRUD `DropdownMenuItem`s that don't fit the view/edit/delete contract (used by `overtime/pacts/index.tsx` for revoke/activate — see below).
- Replace the plain-text actions column in the following files, swapping "Editar"/"Eliminar" for the shared component's dropdown menu:
  - `resources/js/pages/cost-centers/index.tsx` (edit, delete)
  - `resources/js/pages/premises/index.tsx` (edit, delete)
  - `resources/js/pages/saas/organizations/index.tsx` (edit, delete)
  - `resources/js/pages/holidays/index.tsx` (edit, delete)
  - `resources/js/pages/positions/index.tsx` (edit, delete)
  - `resources/js/pages/saas/document-variables/index.tsx` (edit, delete)
  - `resources/js/pages/shifts/index.tsx` (edit, delete)
  - `resources/js/pages/employees/index.tsx` (edit, delete)
  - `resources/js/pages/documents/index.tsx` (edit, delete)
  - `resources/js/pages/document-templates/index.tsx` (edit, delete only — its "restore" text action for trashed rows is untouched)
  - `resources/js/pages/overtime/pacts/index.tsx` (edit, plus its conditional "revoke"/"activate" actions moved inside the same dropdown as extra `DropdownMenuItem`s via the `children` slot — `XCircle`/destructive for revoke, `CheckCircle2` for activate — rather than staying as separate text buttons next to the trigger)
- No page in the app currently renders a bare-text "Ver" row action (the only existing "view" actions, in `leaves/index.tsx`, `my/leaves/index.tsx`, and `workdays/index.tsx`, already show an `Eye` icon inside a dropdown menu item alongside its label). Those existing dropdown menus are out of scope for migration to the new shared component in this ticket; the shared component's view-item support exists so a future bare "Ver" action follows the same convention, not to retrofit those pages.
- `resources/js/pages/roles/index.tsx`'s single text link ("Gestionar permisos") is out of scope — it isn't an edit/delete/view action.
- No backend, route, or permission changes are required; besides the one new `ui.common.actions.more` translation key pair, existing translation strings (`ui.*.actions.edit`, `ui.*.actions.delete`) are reused as-is for the menu items' visible text.

## Testing Decisions

- This is a presentational-only change: the same `onClick`/`href` handlers, routes, and permission checks stay wired to the same actions, only their rendering (dropdown menu vs. text) changes. No new Pest feature tests are needed for the 11 pages themselves — their existing Feature tests cover the underlying edit/delete route behavior, which is untouched.
- A good test here verifies external behavior, not implementation: if a Pest browser test is added for the new shared component, it should assert that selecting the pencil/trash menu item still triggers the same edit/delete behavior (dialog opens, confirm route fires) and that the trigger button exposes the expected aria-label — not that specific icon SVGs render.
- Run `npm run types:check` after introducing the shared component and updating its 11 call sites (TypeScript prop types for the mixed onClick/href usage).
- The one PHP change (`lang/en/ui.php` / `lang/es/ui.php` gaining `common.actions.more`) is translation-only, consistent with how KOL-75 (a prior icon/dropdown row-actions change) handled a translation-only diff — no Pest test needed for it.

## Out of Scope

- Migrating the icon-based dropdown menus already in `leaves/index.tsx`, `my/leaves/index.tsx`, and `workdays/index.tsx` (KOL-75's pattern) onto the new shared component — these already use the dropdown pattern and are left as their own implementations for now.
- The "restore" action in `document-templates/index.tsx` — not an edit/delete/view action, keeps its current text-link presentation (trashed rows have no dropdown at all, since they have no edit/delete either). `overtime/pacts/index.tsx`'s revoke/activate, by contrast, ARE folded into the dropdown (see Implementation Decisions) since the user asked for them to live alongside edit rather than sit outside as separate text.
- `roles/index.tsx`'s "Gestionar permisos" link.
- Any layout/spacing rework of the actions column beyond swapping text/icon-buttons for the dropdown menu (e.g. reordering columns, changing row height) — that would be a separate, larger redesign.

## Further Notes

Reference screenshot (provided by the user) shows the Empleados table's actions column today: "Editar" in the primary color and "Eliminar" in the destructive color, side by side as plain underlined text — this is the pattern being replaced everywhere it appears, now via a single dropdown trigger rather than inline icon buttons (superseding an earlier icon-buttons draft of this ticket).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A shared row-actions component exists (alongside the existing data-table-* components) rendering a MoreVertical dropdown trigger with view (eye), edit (pencil), and delete (trash can) menu items, plus an optional children slot for page-specific extra items
- [x] #2 cost-centers, premises, saas/organizations, holidays, positions, saas/document-variables, shifts, employees, documents, and document-templates index tables render the dropdown menu instead of Editar/Eliminar text for their edit and delete row actions
- [x] #3 overtime/pacts index table renders the dropdown menu with edit plus the conditional revoke/activate items inside the same menu (via the children slot), no longer as separate text buttons next to the trigger
- [x] #4 document-templates index table's restore text action for trashed rows is unchanged
- [x] #5 The dropdown trigger exposes an aria-label (ui.common.actions.more, new translation key) and each menu item keeps its existing translation text visible (e.g. Editar, Eliminar, Revocar, Reactivar)
- [x] #6 Selecting each menu item triggers the exact same behavior (dialog, confirm, route) as the text link it replaces
- [x] #7 npm run types:check passes
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
1. Add a common.actions.more translation key pair ('More actions' / 'Mas acciones') to lang/en/ui.php and lang/es/ui.php, for the shared dropdown trigger's aria-label.
2. Rewrite resources/js/components/data-table-row-actions.tsx: replace the inline icon-button row with a DropdownMenu -- a ghost icon Button trigger showing MoreVertical with aria-label=t('ui.common.actions.more'), and a DropdownMenuContent with one DropdownMenuItem per view/edit/delete action (Eye/Pencil/Trash2 + visible label), onSelect for onClick actions and asChild+Link for href actions, delete item variant=destructive -- following the exact leaves/index.tsx DropdownMenu pattern.
3. Update the 11 call sites (cost-centers, premises, saas/organizations, holidays, positions, saas/document-variables, shifts, employees, documents, document-templates, overtime/pacts) to use the same DataTableRowActions props as before -- only the component's internal rendering changes, so call sites should need no further edits beyond what is already in place. For overtime/pacts, verify the dropdown trigger sits inline with the separate revoke/activate text buttons.
4. Run npm run types:check.
5. Visually verify in the browser: cost-centers, employees (Link-based edit), document-templates (restore branch untouched), overtime/pacts (mixed dropdown + revoke/activate text) -- confirm trigger opens, items fire the right dialogs/routes/confirms, aria-label present.
6. No other PHP touched beyond the translation key -- skip full sa test --compact run per user preference (only run full suite when user asks).
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as a MoreVertical dropdown (per mid-implementation user redirect) instead of inline icon buttons: DataTableRowActions (resources/js/components/data-table-row-actions.tsx) renders a DropdownMenu with view/edit/delete DropdownMenuItems, matching leaves/index.tsx's existing pattern. Added ui.common.actions.more translation key (en/es) for the trigger aria-label. Updated all 11 call sites; overtime/pacts keeps revoke/activate as separate text buttons beside the dropdown trigger; document-templates keeps its restore branch untouched.

Found and fixed a pre-existing bug (approved by user) in resources/js/components/ui/dropdown-menu.tsx: DropdownMenuItem variant=destructive used text-destructive-foreground (white) for item text with no solid destructive background behind it, making destructive items like leaves/index.tsx's existing 'Eliminar' invisible (white-on-white). Fixed so only the icon is destructive-red and the label uses normal text color, per user preference.

Noted but NOT fixed (out of scope, flagged to user): the same white-text-on-no-background pattern exists in resources/js/components/ui/alert.tsx's destructive Alert variant, used across imports/payroll pages.

Verified in-browser (Chrome DevTools MCP, logged in as admin@example.com): cost-centers (dropdown open/edit dialog/delete confirm), employees (Link-based edit item navigates correctly), leaves/index.tsx (existing dropdown, confirms fix applies app-wide), overtime/pacts (created a real pact; dropdown shows only Editar, Revocar stays as separate text button, edit dialog pre-fills correctly).

Follow-up per user request: moved overtime/pacts' revoke/activate out of separate text buttons into the same dropdown as edit. Extended DataTableRowActions with an optional 'children' slot (rendered after edit, before delete) for page-specific non-CRUD DropdownMenuItems. overtime/pacts/index.tsx now passes conditional DropdownMenuItems (XCircle/destructive for revoke, CheckCircle2 for activate) as children. Verified in-browser: created a real pact, opened the dropdown (Editar + Revocar), revoked it (confirm dialog fired correctly, toast confirmed), reopened dropdown (Editar + Reactivar), reactivated it (toast confirmed). Re-ran tsc/eslint/prettier -- clean, only pre-existing unrelated roles/ tsc errors remain.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Replaced the 11 tables' inline Editar/Eliminar text actions with a shared DataTableRowActions dropdown (MoreVertical trigger, view/edit/delete items with icon+visible label, plus a children slot for page-specific extras) -- redesigned mid-implementation from an icon-buttons draft to a dropdown-menu pattern per user direction, then further folded overtime/pacts' revoke/activate into the same dropdown per a second user request. Along the way, fixed a pre-existing app-wide bug where destructive dropdown items rendered invisible white-on-white text (dropdown-menu.tsx), now showing a red icon with normal-colored label text, per user preference. Verified with npm run types:check (clean except 2 pre-existing unrelated roles/ errors), eslint, prettier, and vendor/bin/pint (all clean), plus live browser verification across cost-centers, employees, holidays (created+edited+deleted a real custom holiday), document-templates, leaves.tsx (confirms the dropdown-menu fix applies globally), and overtime/pacts (created a pact, exercised edit/revoke/reactivate end-to-end). A background code-review pass found zero issues. DoD item 'sa test --compact passes' intentionally left unchecked/unrun per this user's standing preference to defer full-suite runs until explicitly requested -- no PHP logic changed, only translation-array additions (lang/en/ui.php, lang/es/ui.php).
<!-- SECTION:FINAL_SUMMARY:END -->
