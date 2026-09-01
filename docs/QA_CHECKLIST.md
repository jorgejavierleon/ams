# Manual QA checklist

### KOL-17 — Record an auditable history of every payroll export

- [ ] Export a payroll report (Excel/CSV/PDF), then open "Historial de exportaciones" in the sidebar and confirm the entry appears with correct user, type, period, format and employee count.
- [ ] Trigger an export with unresolved attendance data, confirm past the warning, and check the history row shows it as warned/confirmed with the right findings in the detail dialog.
- [ ] Filter the history by date range and by report type and confirm the table narrows correctly; try the rows-per-page selector at the bottom of a long list.

### KOL-63 — Show overtime pactos with inline CRUD on the employee Turnos tab

- [ ] Open an employee's Turnos tab, confirm the Pactos card renders below Asignación de turnos with correct spacing.
