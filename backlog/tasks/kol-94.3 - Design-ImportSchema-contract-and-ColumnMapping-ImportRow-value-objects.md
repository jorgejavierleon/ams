---
id: KOL-94.3
title: Design ImportSchema contract and ColumnMapping/ImportRow value objects
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 18:43'
labels:
  - 'wayfinder:grilling'
milestone: m-3
dependencies: []
parent_task_id: KOL-94
type: task
ordinal: 75000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Design the PHP contract for `ImportSchema` (interface methods: field definitions, validation rules, match-key eligibility, target model) and the value-object shapes for `ColumnMapping` and `ImportRow`/`ImportIssue`, following this app's `app/Actions/*` + `app/Services/*` convention. This is resource-agnostic — should be answerable without waiting on the concrete Employee field list, though it should sanity-check against it if that ticket has landed first.
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
ImportSchema contract + ColumnMapping/ImportRow/ImportIssue value objects resolved via grilling (2026-09-03):

1. ImportSchema is a PHP interface (first in app/, matching ValidationRule-style Laravel contracts), implemented per-resource (EmployeeImportSchema first). Methods:
   - fields(): array<int, ImportField>
   - rules(ImportStrategy $strategy, ?Model $existingMatch): array<string, array> — Laravel-native rule arrays, operates on the CAST+resolved row (post reference-resolution, so FK-exists checks run against ids not labels); takes $existingMatch so Rule::unique(...)->ignore($existingMatch) excludes the row's own record on update (without this, every UpdateOnly row would flag itself as a duplicate).
   - resolveReferences(array $row): ReferenceResolution — a readonly VO {resolved: array<string,mixed>, unresolvedFields: list<string>}, not exceptions/nulls. Resolution mechanism (DB lookup for cost_center/premise/position/supervisor vs ContractType enum-label match for contract_type) is entirely internal to the concrete schema — ImportField only flags isReference:bool, no generic target-model/column shape forced onto the interface (doesn't fit the enum case).
   - findExisting(string $matchKey, mixed $normalizedValue): ?Model — resource-specific match-key query.
   - targetModel(): class-string<Model>

2. ImportField: final readonly class (mirrors App\Support\ReportEmployeeFilters convention) with name, label, requiredForCreateOnly (bool), isReference (bool), isMatchKeyEligible (bool), matchKeyComparator (?MatchKeyComparator enum: Exact/CaseInsensitive/NormalizedRut-style cases — closed enum over closures, matches ContractType/ReportExportStatus convention, serializes cleanly across queue boundaries), type (ImportFieldType enum: String/Integer/Decimal/Date/Boolean — drives generic cell-value casting, needed because KOL-94.1 found CSV dates aren't reliably typed and booleans arrive as varied tokens). matchKeys() is NOT a separate interface method — derived by filtering fields() for isMatchKeyEligible.

3. The universal per-row evaluation SEQUENCE is NOT a method on ImportSchema (refines the ticket's original framing) — it's a shared App\Actions\Imports\EvaluateImportRow action (matches app/Actions/* convention for discrete per-row ops) that composes ImportSchema's granular methods. This keeps ImportSchema's surface to pure resource-specific POLICY, and enforces the blank-cell/reference-error rules in exactly one place instead of every future resource re-deriving the algorithm. Sequence: (1) apply ColumnMapping to the raw file row, (2) cast scalars per ImportField.type, (3) resolveReferences() — any unresolvedFields ⇒ whole-row Error (one ImportIssue per unresolved field, no field-level Warning), short-circuit, (4) findExisting() via the chosen match key (when strategy allows update), (5) rules($strategy, $existingMatch) validation against the cast+resolved row, (6) omit any blank non-match-key field from the final resolvedData (encodes "blank ⇒ no-change" once, so ProcessImportRun's eventual $model->fill($importRow->resolvedData) is correct with no special-casing), (7) assemble ImportRow.

4. ColumnMapping: final readonly class {sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: ColumnMappingStatus (Mapped/Unmapped/Ignored)}. Stored as a JSON-cast array column directly on ImportRun (mirrors ReportExport::$filters) — no separate table, nothing here needs independent identity/querying.

5. ImportRow / ImportIssue: ephemeral value objects, NOT persisted as individual DB rows (would explode row counts for large files, contradicts the already-locked chunked-processing design; nothing else in this app persists transient per-row validation state — ReportExport persists only the result). Only aggregate counts land on ImportRun itself. The error-report CSV (94.8) re-runs EvaluateImportRow during the commit job and streams issues to a file rather than reading them back from a table.
   - ImportRow: final readonly class {rowNumber: int, resolvedData: array, status: ImportRowStatus (Ready/Warning/Error/Skipped), issues: list<ImportIssue>, matchedModelId: ?int}
   - ImportIssue: final readonly class {field: ?string (null for whole-row issues like unresolved reference), message: string, severity: ImportIssueSeverity (Warning/Error)}

Out of scope for this ticket (left to KOL-94.4/94.5/94.7/94.8): ProcessImportRun's chunking/idempotency mechanics, wizard endpoint contracts, the auto-mapping algorithm that produces initial ColumnMapping guesses, and error-report CSV column format.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
ImportSchema is a resource-agnostic interface (fields/rules/resolveReferences/findExisting/targetModel) implemented once per resource; the universal per-row evaluation sequence lives in a shared App\Actions\Imports\EvaluateImportRow action rather than on the schema itself, so the blank-cell-no-change and reference-error policies are enforced exactly once. ColumnMapping/ImportRow/ImportIssue/ImportField are readonly value objects (app's existing VO convention); ColumnMapping persists as a JSON column on ImportRun, ImportRow/ImportIssue stay ephemeral. Full contract and rationale in Implementation Notes.
<!-- SECTION:FINAL_SUMMARY:END -->
