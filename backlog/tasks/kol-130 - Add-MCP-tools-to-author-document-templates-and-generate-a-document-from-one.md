---
id: KOL-130
title: Add MCP tools to author document templates and generate a document from one
status: Done
assignee: []
created_date: '2026-09-24 19:33'
updated_date: '2026-09-26 23:26'
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
- [x] #1 Tools exist for: create/update/delete/list document templates, and generate a draft document from a template for a named employee
- [x] #2 New Create:DocumentTemplate/Update:DocumentTemplate/Delete:DocumentTemplate permissions are added to RoleSeeder and granted to admin; DocumentTemplateController now authorizes via Gate::authorize instead of relying solely on role:admin route middleware
- [x] #3 Generating a document never publishes it or triggers the signature flow
- [x] #4 Pest tests cover each tool's success and denial paths
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add AuthorizedTool-based MCP tools under app/Mcp/Tools/DocumentTemplates/ (Create/Update/Delete/List) and app/Mcp/Tools/Documents/GenerateDocumentTool, registered in KolviServer.
2. Retrofit DocumentTemplateController::store/update/destroy to Gate::authorize against Create/Update/Delete:DocumentTemplate instead of relying solely on role:admin middleware.
3. Add Create:DocumentTemplate, Update:DocumentTemplate, Delete:DocumentTemplate, ViewAny:DocumentTemplate (for list-document-templates) and Create:Document (for generate-document) to RoleSeeder's ADMIN_PERMISSIONS, with explanatory comments matching house style.
4. generate-document creates a Draft Document copying the template's raw (unresolved) body/title/type onto the target employee, exactly like the web 'Load Template' + save flow; the MCP response additionally includes the DocumentVariableResolver-resolved body so the calling agent can preview the filled content without altering the stored draft (matches documents/show's preview pattern) or touching the publish/signature flow.
5. Pest feature tests per tool: success + denial paths, in tests/Feature/Mcp/DocumentTemplates/ and tests/Feature/Mcp/Documents/.
6. Run pint --dirty, sail artisan test --compact filtered to Mcp/Document tests, then full suite.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented 5 MCP tools (create/update/delete/list-document-template, generate-document) under app/Mcp/Tools/DocumentTemplates and app/Mcp/Tools/Documents, registered in KolviServer. Retrofitted DocumentTemplateController::store/update/destroy to Gate::authorize. Added ViewAny/Create/Update/Delete:DocumentTemplate and Create:Document permissions to RoleSeeder's ADMIN_PERMISSIONS (ViewAny:DocumentTemplate and Create:Document weren't explicitly named in the ticket text but are needed so a non-owner admin can actually pass authorization for list-document-templates and generate-document -- same pattern KOL-127 used for Create:Leave). generate-document stores the raw (unresolved) template body on the draft, matching the web 'Load Template' + Save flow and the existing freeze-at-publish design; the tool response separately includes a DocumentVariableResolver-resolved preview, mirroring documents/show's existing preview pattern. All new/changed tests pass (Mcp: 71, Document*: 181); pint and Larastan clean on touched files; code-review (medium) found no issues.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added 5 MCP tools (create/update/delete/list-document-template, generate-document) mirroring DocumentTemplateController's CRUD and the web 'Load Template' flow. Retrofitted DocumentTemplateController::store/update/destroy to Gate::authorize against Create/Update/Delete:DocumentTemplate instead of relying solely on role:admin middleware, and seeded those plus ViewAny:DocumentTemplate and Create:Document to admin in RoleSeeder. generate-document creates a Draft Document with the template's raw body (never publishing or triggering signatures), returning a DocumentVariableResolver-resolved preview alongside it. Covered by new Pest tests (success + denial paths, cross-org isolation) in tests/Feature/Mcp/DocumentTemplates and tests/Feature/Mcp/Documents, plus denial-path additions to the existing DocumentTemplateManagementTest.
<!-- SECTION:FINAL_SUMMARY:END -->
