---
id: KOL-110
title: >-
  Align Employee export columns with the import schema so exported files
  re-import cleanly
status: Done
assignee: []
created_date: '2026-09-06 10:53'
updated_date: '2026-09-06 18:35'
labels:
  - bulk-import
  - employees
dependencies: []
references:
  - app/Services/Reports/EmployeeMasterExporter.php
  - app/Services/Imports/EmployeeImportSchema.php
  - app/Actions/Imports/ColumnAutoMapper.php
  - lang/es/ui.php
priority: high
type: bug
ordinal: 97000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Exporting employees (Exportar on /employees) and re-importing that exact file without edits fails to auto-map 3 of 19 columns, including two fields required to create an employee. Root causes: (1) the export's last-name column labels ('Apellido paterno' / 'Apellido materno') don't match the import schema's field labels ('Apellido' / 'Segundo apellido') closely enough to pass the auto-mapper's 0.6 token-overlap threshold, so both are reported 'Sin mapear' and the required field 'Apellido' is flagged missing even though an equivalent column was uploaded; (2) the export has no timezone column at all, yet 'Zona horaria' is required-for-create in the import schema, so a re-imported export can never satisfy that requirement regardless of mapping; (3) the export's 'Empresa' (company) column has no corresponding import field by design (company is auto-assigned per organization, per KOL-32) and every organization has exactly one company, so the column carries no useful information and is unmappable noise -- it should be dropped from the export rather than special-cased in the wizard. This deliberately narrows KOL-23's original 'company' column requirement now that KOL-32 constrains every organization to a single company. Note the separately-generated import template file (EmployeeImportTemplate) already round-trips correctly because it reuses the schema's own labels -- only the human-facing export is out of sync with the importer.

## User stories for manual testing (Gherkin)

### Escenario: Reimportar el export sin correcciones de mapeo
Given un administrador está en la lista de Empleados con trabajadores registrados
When hace clic en "Exportar" y descarga el archivo CSV
And luego va a "Importar empleados" y sube ese mismo archivo sin modificarlo
Then la pantalla de mapeo de columnas muestra todas las columnas mapeadas automáticamente
And no aparece la advertencia de "Campos requeridos sin mapear"

### Escenario: El archivo exportado ya no incluye la columna Empresa
Given un administrador exporta la lista de Empleados a CSV o Excel
When abre el archivo descargado
Then no existe una columna "Empresa" entre las columnas exportadas

### Escenario: Editar un valor y reimportar actualiza al empleado
Given un administrador exporta la lista de Empleados
When edita el teléfono de un trabajador en el archivo exportado
And sube el archivo modificado usando la estrategia de actualización
Then el asistente de importación mapea todas las columnas automáticamente
And el trabajador queda con el teléfono actualizado tras confirmar la importación
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A file downloaded from the Employees export, re-uploaded to the import wizard unmodified, auto-maps last_name and second_last_name with no manual mapping fix required
- [x] #2 The export includes a timezone column labeled to match what the import schema expects, so it auto-maps and satisfies the required-for-create rule on re-import
- [x] #3 Existing employee-export Pest tests are updated for the column changes, and a new test asserts the full export-then-reimport round trip needs zero manual column-mapping fixes for every importable field
- [x] #4 Manual QA: export employees, edit a value in the file, re-import with an update strategy, and confirm no mapping corrections are needed
- [x] #5 The Empresa/company column is removed from the Employee export entirely, since company is auto-assigned per organization (KOL-32) and every organization has exactly one
- [x] #6 Null/blank fields in the export render as empty cells, never a '—' placeholder — a literal dash previously flowed into re-imported reference fields (cost_center/premise/position/contract_type) as an unmatched lookup value, turning every row with any blank optional field into a preview Error
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Root cause confirmed and fixed: EmployeeMasterExporter's last_name/second_last_name labels ('Apellido paterno'/'Apellido materno') now reuse the exact same text as EmployeeImportSchema's form-derived labels ('Apellido'/'Segundo apellido'), so they exact-match (score 1.0) instead of falling below the auto-mapper's 0.6 token-overlap threshold. Added a 'timezone' column (label 'Zona horaria', matching ui.employees.form.timezone) to the export -- was missing entirely, so a re-imported export could never satisfy the required-for-create timezone field. Removed the 'Empresa'/company column from the export entirely (EmployeeMasterExporter, master.blade.php, lang key, and the company eager-load in EmployeeController::export()) per updated decision: every org has exactly one company (KOL-32), so the column was uninformative and always unmappable by design. Updated tests/Feature/EmployeeManagementTest.php's export test for the new column set/labels. Added a new round-trip test in tests/Feature/ImportWizardTest.php that exports a real employee via the employees.export route, re-uploads the file unmodified to imports.employee.store, and asserts every column maps (no 'unmapped' status) and every CreateOnly-required field is present in the mapping. Documented the label-sync invariant in docs/architecture.md under 'One organization, one employer'. pint clean; ImportWizardTest (37/37) and EmployeeManagementTest (51/51) pass; full suite running.

Found via manual QA after the initial fix: master.blade.php rendered every null field as a literal '—' placeholder (common human-display convention), which flowed into the exported CSV as real cell content. EvaluateImportRow::castValue() only treats null/'' as blank -- '—' is a real string, so on re-import every reference field (cost_center/premise/position/contract_type) left blank in the source record produced 'No matching {field} found for "—"' and turned the row into an Error, even though mapping was perfect. Fixed by removing all '?? "—"' fallbacks in master.blade.php so null fields render as empty cells (Blade's e() already handles null safely, no warnings). Added a new test ('an Employee export re-uploaded unmodified previews with zero errors...') that creates an employee with every optional/reference field left null (matching UserFactory::employee()'s defaults), exports, re-uploads, sets UpdateOnly/rut (the realistic re-import path, not CreateOnly which would collide with the just-created record's own unique rut/email), runs preview, and asserts preview_counts is all-Ready. Verified the test fails without the fix (via git stash of just the blade change) and passes with it. Full ImportWizardTest+EmployeeManagementTest suite: 89/89 passing. pint clean.

Per user direction, removed the regression test added for AC #6 (no Pest test for this fix) — the blade fix itself (empty cells instead of '—' for null values) stands, verified manually to fail/pass as expected before removal, and re-confirmed the ImportWizardTest+EmployeeManagementTest suite is back to green (88/88) without it.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Fixed the Employee export/import round-trip: renamed last_name/second_last_name export labels to match the import schema's own labels (exact-match auto-mapping), added the missing timezone column, dropped the uninformative Empresa/company column (KOL-32: one company per org), and removed the '—' null-placeholder from master.blade.php that was turning any blank optional field (cost_center/premise/position/contract_type) into an unmatched-reference preview Error on re-import. Verified via tests/Feature/ImportWizardTest.php's export-then-reimport round-trip test (auto-maps every column, no unmapped required fields) and tests/Feature/EmployeeManagementTest.php's updated export test (asserts Empresa column absent, new column set/labels present); the dash-placeholder fix was additionally verified by temporarily reverting it and confirming a targeted test failed, then passed once reapplied (test itself removed after, per user direction -- no permanent regression test for this narrow view fix). Full suite: 1399 passed / 7 skipped / 0 failed. pint clean. No TypeScript touched.
<!-- SECTION:FINAL_SUMMARY:END -->
