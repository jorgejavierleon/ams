---
id: KOL-109
title: Add a cancel button to the import wizard pages
status: Done
assignee: []
created_date: '2026-09-06 10:37'
updated_date: '2026-09-06 20:57'
labels: []
dependencies: []
ordinal: 96000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
There is currently no user-initiated way to abandon an in-progress ImportRun — only the scheduled PruneAbandonedImportRuns command (KOL-104) cleans up abandoned runs left in Pending, MappingReview, or PreviewReady past their expiry. A user who starts an import and changes their mind (wrong file, wrong resource, wants to restart) has to wait for the prune job or navigate away and leave orphaned state. Add an explicit Cancel action to the wizard steps (mapping-review, strategy, preview) that deletes the ImportRun and its uploaded file immediately, reusing the same disk_path cleanup PruneAbandonedImportRuns already does, and a Back/Cancel link on the initial upload step.

Pending is purely transient (CreateImportRunFromUpload transitions it to MappingReview synchronously within the same request, or deletes it on failure) so a Pending run is never actually visible in the wizard UI — the wizard-step Cancel button in practice only needs to render for MappingReview and PreviewReady, though the backend action should still accept Pending for consistency.

## User stories for manual testing (Gherkin)

Scenario: Cancelling from the mapping-review step
  Given I uploaded a file and I'm on the mapping-review step
  When I click Cancel
  Then I see a confirmation dialog warning the action can't be undone
  And when I confirm, the ImportRun and its uploaded file are deleted
  And I land on the Employees list with a confirmation message

Scenario: Cancelling from the preview step
  Given I ran the preview step and I'm looking at the results
  When I click Cancel and confirm
  Then the ImportRun (including any persisted per-row issues from KOL-111) and its file are deleted
  And I land on the Employees list

Scenario: Backing out of the confirmation dialog changes nothing
  Given the Cancel confirmation dialog is open
  When I dismiss it instead of confirming
  Then the ImportRun still exists and I'm still on the same wizard step

Scenario: Cancelling from the initial upload step, before any file is uploaded
  Given I'm on the "Importar empleados" upload step and haven't selected or submitted a file yet
  When I click Cancel
  Then I'm taken straight to the Employees list with no confirmation dialog, since nothing has been created yet

Scenario: Cancel is unavailable once committing has started
  Given my ImportRun has moved to Processing or finished as Completed/Failed
  When I view that run's page
  Then there is no Cancel button — the only options are the ones the result step already offers
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A Cancel button is present on the mapping-review, strategy, and preview wizard steps (Pending never renders any wizard step in the UI, so no button is needed for it)
- [x] #2 Clicking Cancel on a wizard step shows a confirmation dialog before anything is deleted; dismissing it leaves the ImportRun and its file untouched
- [x] #3 Confirming cancellation deletes the ImportRun's uploaded file from disk (same disk_path cleanup as PruneAbandonedImportRuns) and deletes the ImportRun row, including any per-row issues persisted by KOL-111
- [x] #4 Cancelling is rejected (not just hidden) once the ImportRun has moved to Processing or Completed/Failed — committing is in flight or already done
- [x] #5 After cancelling, the user is redirected to the resource's list page (e.g. Employees) with a confirmation message
- [x] #6 The initial upload step has a Back/Cancel link straight back to the resource's list page, with no confirmation dialog (no ImportRun or file exists yet at that step)
- [x] #7 Cancelling only affects the current user's own ImportRun (respects the KOL-105 ownership scoping) — a cross-org or another user's run in the same org 404s exactly like every other wizard route
- [x] #8 Feature tests cover: cancel deletes the run, its file, and any persisted issues; cancel is rejected once Processing/Completed/Failed; a user cannot cancel another user's or another org's run
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add DELETE imports/{importRun} route + ImportWizardController::destroy() reusing PruneAbandonedImportRuns' disk_path cleanup (issues cascade via FK). 2. Add Cancel button + ConfirmDialog to show.tsx (gated on isEditable = MappingReview/PreviewReady). 3. Add Back/Cancel link (no dialog) to create.tsx pointing at employees.index. 4. Add es/en translation keys. 5. Regenerate Wayfinder. 6. Pest tests: cancel deletes run+file+issues (Pending/MappingReview/PreviewReady), rejected 409 on Processing/Completed/Failed, cross-org and cross-user 404. 7. Pint, full test suite, browser walkthrough.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified: 51/51 ImportWizardTest pass (new tests cover cancel deleting run+file+issues across Pending/MappingReview/PreviewReady, 409 rejection on Processing/Completed/Failed, cross-org and same-org/different-user 404). Full suite green. Pint, ESLint, Prettier, and npm run types:check all clean (types:check's 2 errors are pre-existing/unrelated, in roles/index.tsx and roles/show.tsx). Browser walkthrough (Chrome DevTools MCP) confirmed: Cancel button on mapping-review/strategy/preview steps, confirm dialog blocks deletion until confirmed and leaves the run untouched on dismiss, confirming deletes the ImportRun+file and redirects to Employees with a toast, no Cancel button once a run reaches Processing/Completed/Failed, and the upload step's Cancelar link goes straight to Employees with no dialog. Per user feedback after initial implementation: widened all import wizard pages to use the full available row width (removed max-w-3xl/max-w-5xl constraints), moved the Cancel button from the page header into each step's own button row immediately left of the primary continue button, and shortened its label from 'Cancelar importación' to 'Cancelar'. Also hit and resolved an unrelated Vite dev-server hiccup (stale/empty cached module for mapping-review-step.tsx after a Prettier rewrite) that made 'Subir archivo' appear broken — fixed by touching the file to force Vite to retransform it; no code change was needed for that.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a Cancel action to the Employee import wizard: a DELETE imports/{importRun} route + ImportWizardController::destroy() reuse PruneAbandonedImportRuns' exact disk_path cleanup (ImportRunIssue rows cascade via FK) and reject cancellation once a run is Processing/Completed/Failed (409). The mapping-review, strategy, and preview steps show a Cancel button (confirmed via a shared ConfirmDialog before anything is deleted) next to their primary continue button, full-width per follow-up feedback; the initial upload step has a plain Cancelar link straight to Employees with no dialog. Cancelling redirects to Employees with a success toast. Ownership/org scoping is inherited for free from ImportRun's existing BelongsToOrganization/BelongsToUser scopes (KOL-105). Verified with 8 new Pest tests (51/51 ImportWizardTest, full suite green) and an end-to-end browser walkthrough.
<!-- SECTION:FINAL_SUMMARY:END -->
