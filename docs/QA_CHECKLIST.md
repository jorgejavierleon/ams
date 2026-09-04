# Manual QA checklist

## Pending

### KOL-100 — Add the strategy and match-key selection step to the Employee import wizard

- **Branch:** `feature/kol-100-strategy-match-key-step` (merged to `master`)
- **Deferred on:** 2026-09-04
- **Where:** Importar empleados (`/imports/{importRun}`), as an admin (Import:Employee permission)
- **Automated coverage:** Pest (`tests/Feature/ImportWizardTest.php`) covers saving CreateOnly without a match key, rejecting UpdateOnly without one, persisting strategy+match key while staying at MappingReview, dropping a stray match key on CreateOnly, rejecting an unsupported match key, the 409 status guard, and cross-user/cross-org access.

- [ ] Upload a CSV/Excel file, finish mapping review, and confirm clicking "Guardar y continuar" moves to the strategy step without a page reload (same URL, no flash).
- [ ] Click each of the three strategy cards and confirm only "Solo actualizar" / "Crear y actualizar" reveal the RUT/Email/ID match-key row; "Solo crear" hides it.
- [ ] Pick "Solo actualizar" and confirm "Guardar estrategia" stays disabled until a match key is chosen.
- [ ] Save a strategy, then reload the page — confirm the strategy step (not mapping) shows with the previously saved strategy card and match-key button pre-selected/highlighted.
- [ ] Click "← Mapeo de columnas" from the strategy step, confirm it returns to the mapping table with your prior mapping intact, then step forward again.
- [ ] Check the strategy step at a narrow (mobile) width and in dark mode for layout/contrast issues; watch the browser console for errors throughout.

### KOL-17 — Record an auditable history of every payroll export

- [ ] Export a payroll report (Excel/CSV/PDF), then open "Historial de exportaciones" in the sidebar and confirm the entry appears with correct user, type, period, format and employee count.
- [ ] Trigger an export with unresolved attendance data, confirm past the warning, and check the history row shows it as warned/confirmed with the right findings in the detail dialog.
- [ ] Filter the history by date range and by report type and confirm the table narrows correctly; try the rows-per-page selector at the bottom of a long list.

### KOL-63 — Show overtime pactos with inline CRUD on the employee Turnos tab

- [ ] Open an employee's Turnos tab, confirm the Pactos card renders below Asignación de turnos with correct spacing.
