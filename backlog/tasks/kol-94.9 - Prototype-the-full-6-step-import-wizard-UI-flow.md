---
id: KOL-94.9
title: Prototype the full 6-step import wizard UI flow
status: Done
assignee:
  - '@me'
created_date: '2026-09-02 19:04'
updated_date: '2026-09-03 20:32'
labels:
  - 'wayfinder:prototype'
milestone: m-3
dependencies:
  - KOL-94.7
  - KOL-94.5
parent_task_id: KOL-94
type: task
ordinal: 81000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
## Question

Build a throwaway React/Inertia prototype walking through all 6 wizard steps end-to-end (upload, mapping review, preview, error handling, strategy/match-key selection, final confirmation) against the wizard step-endpoint contract, to validate the step transitions and data each step actually needs/produces feel right before committing to the real implementation.
<!-- SECTION:DESCRIPTION:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Comments

<!-- COMMENTS:BEGIN -->
created: 2026-09-03 20:32
---
Full 6-step wizard prototype (Upload -> Mapping review -> Strategy & match key -> Preview & validation -> Confirm & commit -> Result) resolved via a working React/Inertia prototype, walked end-to-end live in-browser (Chrome DevTools MCP, no console errors) at prototype/import-wizard.

Screen order interpretation: 'preview' and 'error handling' from the ticket's list fold into one screen, since the real contract (KOL-94.5) only ever gives that screen aggregate preview_counts, never a per-row breakdown or an error-report download pre-commit (the CSV itself isn't generated until ProcessImportRun's commit pass per KOL-94.8). Screens, in order: (1) Upload — sample-file buttons stand in for a real file since no backend exists; template downloads offered here. (2) Mapping review — KOL-94.7's winning Variant A reused verbatim (flat table, 0.6 threshold, inline Combobox Fix flow), lifted into shared wizard state so choices persist across Back/Next. (3) Strategy & match key — CreateOnly/UpdateOnly/CreateAndUpdate cards; match-key picker (RUT/Email/ID) only appears when the strategy needs one. (4) Preview — aggregate Ready/Warning/Error/Skipped counts only (no per-row grid, matching KOL-94.3/94.5's persisted-aggregates-only design); two fixture buttons simulate a clean vs. an issues-present run. (5) Confirm & commit — full run summary plus a prototype-only 'simulate what the queued job does' control (clean / partial-errors / job failure), since no real ProcessImportRun exists to produce an outcome. (6) Result — Processing spinner (setTimeout stands in for the real usePoll loop) resolving to Completed (with an error-report download once errored > 0) or Failed (terminal, 'start over' only, no retry route).

Verified live: the PreviewReady -> MappingReview demotion rule from KOL-94.5 (editing mapping or strategy after preview clears preview_counts and re-locks the Confirm step until preview reruns) works exactly as specified — confirmed by walking the demotion path in-browser, not just by reading the code.

Full prototype (12 files: wizard shell + 6 step components + types/fixtures/mapping-algorithm/progress-nav) preserved on throwaway branch prototype/kol-94-9-import-wizard (commit ad54e8a) — deleted from main per the prototype skill's cleanup step. No open questions carried forward; this was the map's last open ticket.
---
<!-- COMMENTS:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Full 6-step React/Inertia wizard prototype (Upload, Mapping review, Strategy & match key, Preview & validation, Confirm & commit, Result) built and verified live in-browser end-to-end, including the PreviewReady->MappingReview demotion path. Preserved on throwaway branch prototype/kol-94-9-import-wizard (commit ad54e8a); deleted from main. Map's last open ticket — KOL-94 has no remaining decisions.
<!-- SECTION:FINAL_SUMMARY:END -->
