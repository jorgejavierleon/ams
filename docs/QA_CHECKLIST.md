# Manual QA checklist

### KOL-63 — Show overtime pactos with inline CRUD on the employee Turnos tab

- [ ] Open an employee's Turnos tab, confirm the Pactos card renders below Asignación de turnos with correct spacing.
- [ ] Add, edit, revoke and reactivate a pacto from the card and confirm each action updates the table without navigating away.
- [ ] Confirm the add/edit dialog has no employee picker (employee is implicit) and dates/validation errors render correctly.

### KOL-44 — Build the pending-overtime queue with individual and bulk approve or object

- [ ] As a supervisor, open Horas extra → Horas extra pendientes and confirm only direct reports' pending days appear; as an admin, confirm the whole organization appears.
- [ ] Approve a clean pending day, editing the authorized hours down from the calculated figure; confirm the row moves out of the Pendiente tab and the final hours reflect the lower amount.
- [ ] Object to a clean pending day without a reason and confirm the form blocks submission until a reason is entered.
- [ ] Attempt to approve a flagged day and confirm the error shown names the actual anomaly reason (not a generic error).
- [ ] Select a mix of clean and flagged pending rows and bulk-approve; confirm the clean rows get approved and the flagged row stays pending.
- [ ] Bulk-object a selection with a reason and confirm all selected rows move to Objetada.
- [ ] Filter the queue by period and by employee and confirm the list updates accordingly.
- [ ] Confirm every label on the screen (tabs, columns, dialogs, buttons, flash messages) is in Spanish.
