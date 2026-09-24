---
id: KOL-130
title: Add MCP tools to author document templates and generate a document from one
status: To Do
assignee: []
created_date: '2026-09-24 19:33'
labels:
  - mcp
  - document-templates
  - backend
dependencies:
  - KOL-126
priority: medium
type: feature
ordinal: 130000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Two related capabilities, both stopping short of publish/signature (a follow-up ticket can add a `publish` tool once this pattern is proven):
1. Template authoring — CRUD on `DocumentTemplate` (title, type, body with `{{variable}}` placeholders) via an agent, equivalent to `DocumentTemplateController`'s existing admin UI.
2. Document generation — given a template and a target employee, create a filled, draft `Document` (variables resolved), matching what "Load Template" already does in the document editor. The agent's job stops at creating the draft; a human still reviews/publishes/sends it for signature in the UI.

`DocumentTemplateController` today is gated only by `role:admin` route middleware, not a permission. This ticket adds real Spatie permissions — reusing `DocumentTemplatePolicy`'s existing-but-currently-unused ability names (`Create:DocumentTemplate`, `Update:DocumentTemplate`, `Delete:DocumentTemplate`) rather than inventing new ones — seeds them to `admin`, and retrofits `DocumentTemplateController` to `Gate::authorize` against them instead of relying on the route-level role check.

## User stories for manual testing (Gherkin)

Scenario: An admin's agent creates a new document template
  Given an admin has a valid MCP session
  When their agent calls the create-document-template tool with a title, type, and body containing {{variable}} placeholders
  Then a new DocumentTemplate exists, visible in the Document Templates list in the web app

Scenario: An admin's agent generates a document from a template for an employee
  Given an admin has a valid MCP session and a document template exists
  When their agent calls the generate-document tool with that template and a target employee
  Then a draft Document is created for that employee with its variables resolved, and it is not yet published or sent for signature

Scenario: A non-admin's agent is denied
  Given a user without Create:DocumentTemplate has a valid MCP session
  When their agent calls the create-document-template tool
  Then the tool refuses
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Tools exist for: create/update/delete/list document templates, and generate a draft document from a template for a named employee
- [ ] #2 New Create:DocumentTemplate/Update:DocumentTemplate/Delete:DocumentTemplate permissions are added to RoleSeeder and granted to admin; DocumentTemplateController now authorizes via Gate::authorize instead of relying solely on role:admin route middleware
- [ ] #3 Generating a document never publishes it or triggers the signature flow
- [ ] #4 Pest tests cover each tool's success and denial paths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->
