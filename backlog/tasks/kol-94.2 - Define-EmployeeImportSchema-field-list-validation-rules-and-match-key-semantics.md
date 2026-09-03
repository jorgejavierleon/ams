---
id: KOL-94.2
title: >-
  Define EmployeeImportSchema field list, validation rules, and match-key
  semantics
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 15:55'
labels:
  - 'wayfinder:grilling'
milestone: m-3
dependencies: []
parent_task_id: KOL-94
type: task
ordinal: 74000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Pin down the concrete `EmployeeImportSchema` (see CONTEXT.md for the ImportSchema/Match key definitions): the exact list of importable fields (starting from `EmployeeMasterExporter::prepare()`'s column set), which validation rules from `EmployeeController::validateEmployee()` apply per-field on import (RUT/email uniqueness scoped per org, FK existence for cost_center_id/premise_id/position_id/supervisor_id, ContractType enum, timezone), and the exact semantics of each match key (RUT, Email, ID) — e.g. RUT normalization/formatting before comparison, whether Email match is case-insensitive, whether ID means the `users.id` primary key from a prior export.

Also decide: which fields are required for CreateOnly vs optional for UpdateOnly (e.g. can an update-only row omit fields it isn't changing?).
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
EmployeeImportSchema resolved via grilling (2026-09-03):

1. Field list = full parity with the manual create/edit form: the 19-column export set (first_name, last_name, second_last_name, rut, email, personal_email, phone, nationality, gender, cost_center, premise, position, contract_type, contract_start_date, contract_end_date, emergency_contact_name, emergency_contact_phone, is_active) PLUS supervisor (by RUT), is_admin, timezone, vacation_days, additional_vacation_days, administrative_days, has_additional_sundays, overtime_rest_day_eligible. Company excluded (auto-assigned per org, KOL-32). Password and avatar excluded entirely.

2. Password: User::create gets an auto-generated random password on import; employee resets it via the existing forgot-password flow before first login. No new invitation-email infra.

3. Reference-field resolution (cost_center, premise, position, contract_type, supervisor): case-insensitive exact match against the org's existing names / the 4 known Spanish contract_type labels / supervisor's RUT. No match => the whole ImportRow is an Error, never a per-field Warning. (Now captured as CONTEXT.md's "Reference field" term.)

4. Match-key comparison: RUT normalized via the existing App\Support\Rut::normalize() before comparing; Email lowercased before comparing; ID is an exact integer match against users.id.

5. Blank-cell semantics on UpdateOnly/CreateAndUpdate: blank in a non-match-key column = no change to the existing value, never clear-to-null. (Now captured in CONTEXT.md's "Import strategy" entry.)

6. CreateOnly required fields mirror the manual form exactly: first_name, last_name, email, rut, timezone. Everything else optional, matching the form's own nullable rules.

7. Uniqueness scope: rut/email/personal_email uniqueness on import mirrors the existing GLOBAL (not org-scoped) Rule::unique used by the manual form -- deliberately not introducing per-org uniqueness scoping as a side effect of this importer.

Per-field validation otherwise inherits validateEmployee() as-is (App\Http\Controllers\EmployeeController.php:453-498): ValidRut, email/personal_email format, FK existence checks (org-scoped) once resolved from name to id, ContractType::class enum, contract_end_date after_or_equal:contract_start_date, numeric/min:0 on day balances, booleans on flags.

ID match key is only meaningful when the ImportRun's strategy allows updates (UpdateOnly/CreateAndUpdate); under CreateOnly it is not applied.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
EmployeeImportSchema pinned down: field list (export set + supervisor/is_admin/timezone/vacation balances), reference-field resolution policy (exact match or row Error), match-key comparison semantics, blank-cell=no-change on update, CreateOnly required set, and uniqueness scope (mirrors existing global Rule::unique). Full detail in Implementation Notes. CONTEXT.md updated with 'Reference field' term and Import strategy's blank-cell clause.
<!-- SECTION:FINAL_SUMMARY:END -->
