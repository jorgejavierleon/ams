---
id: KOL-117
title: Replace text Editar/Eliminar/Ver row actions with icon buttons
status: To Do
assignee: []
created_date: '2026-09-18 17:54'
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

Across the app's data tables, the row actions to edit or delete a record are rendered as plain underlined text links ("Editar", "Eliminar") side by side in the actions column. On tables with many rows this reads as visual clutter and is inconsistent with the icon-based row actions already used elsewhere in the app (e.g. the Jornadas and Solicitudes dropdown menus, which pair an icon with each action).

## Solution

Replace the plain-text "Editar" / "Eliminar" (and, going forward, "Ver") row actions with icon-only buttons: a pencil for edit, a trash can for delete, and an eye for view. Introduce one shared row-actions component so every table renders these consistently instead of each page hand-rolling its own text buttons, and use it everywhere the plain-text pattern currently exists.

## User Stories

1. As a user scanning a long table (e.g. Empleados, Centros de costo, Feriados), I want row actions shown as compact icons instead of text, so that the actions column takes less horizontal space and the table is easier to scan.
2. As a user relying on a screen reader, I want each icon-only action to still announce "Editar"/"Eliminar" (etc.) via an accessible name, so removing the visible text doesn't remove the information.
3. As a developer adding a new table to the app, I want a single shared row-actions component to import, so I don't re-implement icon-button markup, styling, and aria-labels from scratch on every new page.
4. As a developer maintaining an existing table (e.g. Plantillas de documentos, Pactos de horas extra) that has a non-CRUD text action alongside edit/delete (restore, revoke, activate), I want those actions left untouched, so the change doesn't force a redesign of actions outside its scope.
5. As a user of the Empleados table, I want the edit and delete icons to keep triggering the exact same dialogs/routes they do today, so the change is purely visual and doesn't alter existing behavior.
6. As a future user of a table that gains a "view" row action, I want it to already follow the eye-icon convention, so newly added view actions are visually consistent with edit/delete without another redesign pass.

## Implementation Decisions

- Add one new shared component (following the existing `resources/js/components/data-table-*.tsx` naming convention, e.g. `data-table-row-actions.tsx`) that renders icon-only row-action buttons: `Pencil` for edit, `Trash2` for delete, `Eye` for view — matching the icons already used for these same concepts in `resources/js/pages/leaves/index.tsx`'s dropdown menu (`Trash2`, `Eye`) and `resources/js/pages/employees/show.tsx` (`Pencil`). Standardize on these three icons rather than the mixed `Pencil`/`PenLine`/`PencilLine` currently used ad hoc across the app.
- Each button in the shared component keeps an `aria-label` set to the existing translation string (e.g. `t('ui.cost_centers.actions.edit')`), so the accessible name is unchanged even though the visible text is removed. No tooltip is required — this matches the existing icon-only row actions in `leaves/index.tsx` (approve/reject), which rely on `aria-label` alone.
- The shared component accepts the action handlers/hrefs as props (works for both `<button onClick>` actions like edit-opens-dialog/delete-opens-confirm, and `<Link href>` actions like document-templates' edit), since the current per-page implementations mix both.
- Replace the plain-text actions column in the following files, swapping "Editar"/"Eliminar" for the shared component's icon buttons:
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
  - `resources/js/pages/overtime/pacts/index.tsx` (edit only — its "revoke"/"activate" text actions are untouched)
- No page in the app currently renders a bare-text "Ver" row action (the only existing "view" actions, in `leaves/index.tsx`, `my/leaves/index.tsx`, and `workdays/index.tsx`, already show an `Eye` icon inside a dropdown menu item alongside its label). Those dropdown menus are out of scope; the shared component's view-icon support exists so a future bare "Ver" action follows the same convention, not to retrofit the dropdowns.
- `resources/js/pages/roles/index.tsx`'s single text link ("Gestionar permisos") is out of scope — it isn't an edit/delete/view action.
- No backend, route, translation-key, or permission changes are required; only the JSX rendering of the actions cell changes. Existing translation strings (`ui.*.actions.edit`, `ui.*.actions.delete`) are reused as-is for the `aria-label`s, not removed.

## Testing Decisions

- This is a presentational-only change: the same `onClick`/`href` handlers, routes, and permission checks stay wired to the same buttons, only their rendering (icon vs. text) changes. No new Pest feature tests are needed for the 11 pages themselves — their existing Feature tests cover the underlying edit/delete route behavior, which is untouched.
- A good test here verifies external behavior, not implementation: if a Pest browser test is added for the new shared component, it should assert that clicking the pencil/trash icon still triggers the same edit/delete behavior (dialog opens, confirm route fires) and that each icon button exposes the expected `aria-label` — not that specific icon SVGs render.
- Run `npm run types:check` after introducing the shared component and updating its 11 call sites (TypeScript prop types for the mixed onClick/href usage).
- No PHP is touched, so the "every PHP change has a Pest test" DoD item does not apply, consistent with how KOL-75 (a prior icon/dropdown row-actions change) handled a translation-only diff.

## Out of Scope

- The icon-based dropdown menus already in `leaves/index.tsx`, `my/leaves/index.tsx`, and `workdays/index.tsx` (KOL-75's pattern) — these already use icons, not plain text, and are a different interaction pattern (kebab menu) from the inline icon buttons this ticket introduces.
- The "restore" action in `document-templates/index.tsx` and the "revoke"/"activate" actions in `overtime/pacts/index.tsx` — these aren't edit/delete/view and keep their current text-link presentation.
- `roles/index.tsx`'s "Gestionar permisos" link.
- Any layout/spacing rework of the actions column beyond swapping text for icons (e.g. adding a kebab/dropdown menu) — that would be a separate, larger redesign.

## Further Notes

Reference screenshot (provided by the user) shows the Empleados table's actions column today: "Editar" in the primary color and "Eliminar" in the destructive color, side by side as plain underlined text — this is the pattern being replaced everywhere it appears.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A shared row-actions component exists (alongside the existing data-table-* components) rendering icon-only edit (pencil), delete (trash can), and view (eye) actions
- [ ] #2 cost-centers, premises, saas/organizations, holidays, positions, saas/document-variables, shifts, employees, documents, and document-templates index tables render icon buttons instead of Editar/Eliminar text for their edit and delete row actions
- [ ] #3 overtime/pacts index table renders an icon button instead of Editar text for its edit row action, leaving revoke/activate as text
- [ ] #4 document-templates index table's restore text action for trashed rows is unchanged
- [ ] #5 Each icon-only action button exposes an aria-label with the same Spanish text the removed visible label had (e.g. Editar, Eliminar)
- [ ] #6 Clicking each icon button triggers the exact same behavior (dialog, confirm, route) as the text link it replaces
- [ ] #7 npm run types:check passes
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
