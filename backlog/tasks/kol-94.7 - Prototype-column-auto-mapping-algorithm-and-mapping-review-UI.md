---
id: KOL-94.7
title: Prototype column auto-mapping algorithm and mapping-review UI
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 19:56'
labels:
  - 'wayfinder:prototype'
milestone: m-3
dependencies:
  - KOL-94.2
parent_task_id: KOL-94
type: task
ordinal: 79000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Build a throwaway prototype of the Step 2 "automatic mapping" screen from the original mockup: given a set of uploaded column headers and the EmployeeImportSchema's field list (from the schema-definition ticket), what matching algorithm (exact/synonym/fuzzy, confidence threshold) decides Mapped vs Unmapped, and what does the "Fix" flow look like for an unmapped/low-confidence column? Use this to sanity-check the ColumnMapping value object shape.
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
Column auto-mapping algorithm + mapping-review UI resolved via prototype (2026-09-03): three UI variants built against a deliberately messy 28-column fake upload (Spanish HR-export-style headers vs EmployeeImportSchema's 26 fields) -- A) flat table with a single confidence threshold (0.6) and an inline dropdown Fix flow, B) three confidence tiers (0.85/0.55) with a confirm-gate on medium guesses, C) triage queue hiding auto-mapped columns behind a disclosure and showing only what needs a decision, with sample cell values per column. All three verified live in-browser (Chrome DevTools MCP), no console errors.

Decision: Variant A wins -- binary threshold, flat table, no confidence tiers surfaced in the UI. The Fix flow for both Unmapped and already-Mapped rows is the same inline Combobox (search + select over all schema fields, plus an explicit 'Ignore this column' option). This sanity-checks that ColumnMapping's shape from KOL-94.3 (sourceColumnIndex, sourceHeaderLabel, targetField, status: Mapped/Unmapped/Ignored) is sufficient -- no confidence/score field is needed on the real VO since the UI never surfaces one.

Accepted, unresolved risk carried forward: short 2-3 letter aliases (e.g. CC for cost_center, TZ for timezone) can score 100% confidence off an exact-but-coincidental match -- not solved here, worth a second look if false-positive auto-maps show up in practice. The 0.6 score cutoff is this prototype's placeholder, not a locked number -- the real ColumnAutoMapper's matching strategy (exact/synonym/fuzzy scoring) and its threshold are follow-on implementation decisions, out of this ticket's scope.

Full prototype (all 3 variants + algorithm sketch + fixtures) preserved on throwaway branch prototype/kol-94-7-import-mapping (commit 59b4bdc) -- deleted from main per the prototype skill's cleanup step.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Column auto-mapping + mapping-review UI resolved: flat single-threshold table (Variant A) wins over confidence-tiered and triage-queue alternatives. Confirms ColumnMapping's Mapped/Unmapped/Ignored shape needs no confidence field; Fix flow is one inline searchable Combobox per row plus an Ignore option. Full 3-variant prototype on throwaway branch prototype/kol-94-7-import-mapping (59b4bdc).
<!-- SECTION:FINAL_SUMMARY:END -->
