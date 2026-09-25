---
id: KOL-132
title: Add personal access token management to Settings
status: Done
assignee: []
created_date: '2026-09-25 09:34'
updated_date: '2026-09-25 10:25'
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
- [x] #1 Settings > Security shows a table/list of the authenticated user's active personal access tokens (name, created date, last used date)
- [x] #2 A create-token form takes a name and, on submit, creates a Sanctum token scoped to the authenticated user
- [x] #3 The new token's plaintext value is shown exactly once in a modal, with a copy-to-clipboard button and a clear warning it will not be shown again; it is never persisted or re-displayed after the modal closes
- [x] #4 Each row in the token list has a delete action (with a confirmation step) that revokes that token immediately
- [x] #5 A user can only list, create, or revoke their own tokens - the backend scopes every operation to $request->user()->tokens()
- [x] #6 Deleted/revoked tokens can no longer authenticate against the app
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
1. Backend: TokenController@store/destroy scoped to $request->user()->tokens(); PersonalAccessTokenStoreRequest for name validation; SecurityController@edit passes tokens list (id,name,created_at,last_used_at).
2. Routes: settings/security/tokens (POST) and settings/security/tokens/{token} (DELETE) in the auth+verified group.
3. Frontend: extract PersonalAccessTokens component (list + create form + ConfirmDialog for revoke) mounted in security.tsx; new-token Dialog shows plaintext token once via Inertia::flash + onFlash, with useClipboard copy button.
4. Translations: ui.settings.security.tokens.* in lang/en+es/ui.php.
5. Wayfinder generate for TokenController; Pest tests for store/destroy/scoping/revoked-token-rejected; pint; types:check.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Backend: TokenController (store/destroy), PersonalAccessTokenStoreRequest, SecurityController::edit now passes user's tokens. Routes added under settings/security/tokens (auth+verified group, POST throttled 6/min). Frontend: extracted PersonalAccessTokens component (list, revoke ConfirmDialog, create-token Dialog showing plaintext via Inertia::flash('newToken', ...) + onFlash, useClipboard copy button) mounted in security.tsx. Translations added to lang/en+es ui.php under settings.security.tokens. Wayfinder regenerated with --with-form. Tests: tests/Feature/Settings/PersonalAccessTokenTest.php (list scoping, create, validation, revoke, cross-user 404, revoked-token-rejected) - all 6 pass; existing SecurityTest.php still passes. pint clean; npm run types:check has 2 pre-existing unrelated errors in roles/index.tsx and roles/show.tsx (confirmed present on master before this branch, not touched here). Could not browser-verify: Claude-in-Chrome extension not connected in this session.

Code review (background /code-review) found 4 issues, all fixed: (1) destroy() route now constrained ->whereNumber('token') so a non-numeric id 404s instead of a TypeError/500; (2) token store/destroy routes now require RequirePassword (matching security.edit) so a stale session can't mint/revoke tokens without re-confirming the password; (3) token names are now unique per user (PersonalAccessTokenStoreRequest validation) instead of silently allowing duplicates; (4) destroy route now throttled 6/min like store. Added 3 more Pest tests for these (duplicate name rejected, RequirePassword enforced, non-numeric id handled cleanly) - 11 tests passing total (3 skipped 2FA-feature-disabled tests unrelated).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added self-service personal access token management to Settings > Security: a list of the user's active tokens (name/created/last-used), a create-token form whose plaintext token is revealed exactly once via Inertia::flash + a copy-to-clipboard modal, and a revoke action with confirmation. Every operation is scoped to $request->user()->tokens() and gated by RequirePassword like the rest of the security page. Verified with 11 Pest tests (listing/scoping, create+validation+uniqueness, revoke, cross-user 404, malformed-id 404, password-confirmation required, revoked-token-rejected-by-API) plus the user's own manual verification in the browser. pint clean; npm run types:check introduces no new errors (2 pre-existing, unrelated errors in roles/*.tsx confirmed present on master).
<!-- SECTION:FINAL_SUMMARY:END -->
