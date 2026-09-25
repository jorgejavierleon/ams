---
id: KOL-132
title: Add personal access token management to Settings
status: To Do
assignee: []
created_date: '2026-09-25 09:34'
labels:
  - settings
  - security
dependencies: []
ordinal: 132000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Users currently have no self-service way to create or revoke a Sanctum personal access token (used for the mobile API and for connecting an AI agent via the KOL-126/127 MCP server) — today an admin has to mint one by hand in tinker. Add a token management panel to the existing Settings > Security page (resources/js/pages/settings/security.tsx): a list of the user's own active tokens with a delete action, and a create-token flow that shows the plaintext token exactly once in a modal with a copy button and a standard 'copy this now, we will not show it again' warning, following the usual GitHub/GitLab-style personal access token UX.

## User stories for manual testing (Gherkin)

Scenario: A user creates a new personal access token
  Given a user is on the Settings > Security page
  When they enter a name and submit the create-token form
  Then a modal shows the plaintext token exactly once, with a copy-to-clipboard button and a warning that it will not be shown again
  And after closing the modal the new token appears in the token list without its plaintext value

Scenario: A user revokes an existing token
  Given a user has at least one active personal access token
  When they choose the delete action on that token and confirm
  Then the token disappears from the list and requests using that token are rejected

Scenario: A user only ever sees their own tokens
  Given two different users each have active personal access tokens
  When either user opens the Settings > Security page
  Then they see only their own tokens, never the other user's
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Settings > Security shows a table/list of the authenticated user's active personal access tokens (name, created date, last used date)
- [ ] #2 A create-token form takes a name and, on submit, creates a Sanctum token scoped to the authenticated user
- [ ] #3 The new token's plaintext value is shown exactly once in a modal, with a copy-to-clipboard button and a clear warning it will not be shown again; it is never persisted or re-displayed after the modal closes
- [ ] #4 Each row in the token list has a delete action (with a confirmation step) that revokes that token immediately
- [ ] #5 A user can only list, create, or revoke their own tokens - the backend scopes every operation to $request->user()->tokens()
- [ ] #6 Deleted/revoked tokens can no longer authenticate against the app
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
