---
id: KOL-124
title: 'Dashboard: Document signature compliance widget'
status: To Do
assignee: []
created_date: '2026-09-21 08:55'
labels: []
dependencies: []
ordinal: 121000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Add a widget to the dashboard showing document signature compliance: the share of DocumentSignature rows still pending versus signed, broken down by document type, for the current organization. Uses the existing signing workflow (My\DocumentController::sign/reject, DocumentSignatureStatus) — no new signing logic needed, just aggregation. Gate on ViewAny:Document, the same permission guarding the documents index, since document oversight isn't scoped per supervisor team today.

## User stories for manual testing (Gherkin)

Scenario: Compliance widget for an authorized user
  Given there are Document records with a mix of pending and signed DocumentSignature rows across several document types
  When a user holding ViewAny:Document visits the dashboard
  Then they see, per document type, the percentage of required signatures still pending

Scenario: Fully compliant organization
  Given every DocumentSignature in the organization is signed
  When the user visits the dashboard
  Then the widget shows 100% signed with no pending indicator

Scenario: No permission
  Given a user does not hold ViewAny:Document
  When they visit the dashboard
  Then the document compliance widget is not shown

Scenario: No documents yet
  Given the organization has no Document records
  When the user visits the dashboard
  Then the widget shows a clear empty state rather than a division-by-zero
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Widget shows, per DocumentType, the percentage of DocumentSignature rows pending vs signed (rejected/cancelled tracked separately, not counted as pending)
- [ ] #2 Visible only to users holding ViewAny:Document
- [ ] #3 Explicit empty state when the organization has no documents
- [ ] #4 A Pest test covers the per-type percentages, the fully-compliant case, permission scoping, and the empty state
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
