# AMS

Workforce and attendance management system for Chilean employers — tracks employees, schedules, payroll, overtime, and DT (Dirección del Trabajo) compliance reporting.

## Language

### Bulk Import

**ImportRun**:
A single execution of a bulk import: one uploaded file, moving through mapping, preview, and commit. Statuses: Pending, MappingReview, PreviewReady, Processing, Completed, Failed.
_Avoid_: ImportJob (collides with Laravel's own Job concept), DataImport

**ImportSchema**:
The per-resource configuration describing how to import a given resource: its importable columns, validation rules, available match keys, and target model. One ImportSchema exists per importable resource (e.g. EmployeeImportSchema).
_Avoid_: ImportProfile, ImportConfig

**ColumnMapping**:
The pairing of one column in the uploaded file to one field on an ImportSchema. Status is Mapped, Unmapped (needs a manual pick), or Ignored (deliberately excluded from the import).
_Avoid_: FieldMapping

**ImportRow**:
One data row from the uploaded file, evaluated against an ImportSchema. Status is Ready, Warning, Error, or Skipped.
_Avoid_: ImportRecord

**ImportIssue**:
A problem found on a single ImportRow during validation, with a severity of Warning or Error. A Warning still allows the row to import; an Error excludes it.
_Avoid_: ImportError (too narrow — issues include warnings too)

**Match key**:
The field used to find an existing record that an ImportRow should update, when the ImportRun's strategy allows updates. Chosen by the user per ImportRun from the identifier fields an ImportSchema marks as eligible (for Employees: RUT, Email, or ID).
_Avoid_: External ID, Employee ID (neither is a real field in this app)

**Import strategy**:
One of three modes governing what an ImportRun is allowed to do with matched/unmatched rows: CreateOnly, UpdateOnly, or CreateAndUpdate. Under UpdateOnly and CreateAndUpdate, a blank cell in a non-match-key column means no change to the existing value — never a clear-to-null.
_Avoid_: Import mode

**Reference field**:
An ImportSchema field whose cell value identifies another record by a human-readable label rather than holding data directly (e.g. EmployeeImportSchema's cost_center, premise, position, contract_type, and supervisor). Resolved by case-insensitive exact match against the target's existing values; an unresolved reference makes the whole ImportRow an Error, never a partial/Warning on just that field.
_Avoid_: Foreign key field (implementation-level, not a glossary concept)
