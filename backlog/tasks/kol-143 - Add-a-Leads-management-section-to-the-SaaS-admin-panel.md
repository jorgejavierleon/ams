---
id: KOL-143
title: Add a Leads management section to the SaaS admin panel
status: Done
assignee:
  - jorgejavierleon@gmail.com
created_date: '2026-10-04 21:04'
updated_date: '2026-10-04 21:10'
labels:
  - saas
  - feature
dependencies: []
ordinal: 165000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The public landing page's contact form (KOL-139) stores submissions in the leads table (app/Models/Lead.php), but nothing in the app lets anyone read them except a direct DB query. Add a read-only Leads list to the existing super-admin SaaS panel (app/Http/Controllers/Saas/, resources/js/pages/saas/) so the saas role can review and action new leads. Follow the existing SaaS panel conventions: DataTable foundation (useServerTable/DataTable, see KOL-58), ResolvesTableSort/ResolvesTablePerPage concerns, search+sort+paginate index pattern as in Saas/OrganizationController and Saas/AuditLogController, and a nav link in SaasLayout.tsx. Leads have no organization_id (KOL-139 note: organization-less by design) so this query is intentionally unscoped.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A 'Leads' nav link appears in the SaaS panel layout, gated the same way as the other super-admin links (role:saas,saas)
- [x] #2 GET /saas/leads lists leads with name, company, email, message and created_at, newest first by default
- [x] #3 The list supports search (by name/company/email) and column sort, consistent with other SaaS panel tables
- [x] #4 A super-admin can delete a lead from the list
- [x] #5 Non-saas-role users cannot access the route (403 or redirect, matching existing SaaS panel route protection)
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
1. Backend: add LeadController@index (search by name/company/email, sort by name/company/email/created_at, paginate via ResolvesTableSort/ResolvesTablePerPage) and @destroy in app/Http/Controllers/Saas/LeadController.php, following Saas/OrganizationController and Saas/AuditLogController conventions.
2. Routes: add Route::get('saas/leads', ...)->name('leads.index') and Route::delete('saas/leads/{lead}', ...)->name('leads.destroy') inside the existing role:saas,saas group in routes/web.php.
3. Frontend: add resources/js/pages/saas/leads/index.tsx modeled on saas/organizations/index.tsx (DataTable + DataTableColumnHeader + ConfirmDialog for delete, no create/edit since leads are landing-page-submitted only).
4. Add a 'Leads' nav link to resources/js/layouts/SaasLayout.tsx next to the other saas.* nav links.
5. Add lang/en/ui.php and lang/es/ui.php 'leads' translation block (title, description, columns, search_placeholder, empty, actions.delete, delete_dialog, flash.deleted) mirroring the organizations block.
6. Run npm run build / wayfinder generation so routes/saas/leads ts helpers exist.
7. Add tests/Feature/SaasLeadsTest.php mirroring SaasAuditLogTest.php: access control (guest redirect, non-saas forbidden), index listing + search, delete.
8. Run vendor/bin/pint --dirty --format agent and sail artisan test --compact --filter=SaasLeads.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented Saas/LeadController (index+destroy), routes under role:saas,saas group, saas/leads/index.tsx DataTable page, SaasLayout nav link, en/es ui.php 'leads' translation block, tests/Feature/SaasLeadsTest.php (6 tests). Verified in browser: list renders with search/sort/pagination, delete flow works with confirm dialog + flash toast. pint clean, types:check clean, eslint clean, all tests pass.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a read-only Leads section to the SaaS admin panel: Saas/LeadController (index with search/sort/paginate, destroy), routes/saas/leads under the existing role:saas,saas group, saas/leads/index.tsx using the DataTable foundation, a 'Leads' nav link in SaasLayout.tsx, and en/es translations. Verified with tests/Feature/SaasLeadsTest.php (6 passing tests covering access control, listing, search, delete) plus a live browser walkthrough (login as a saas user, view seeded leads, search, delete with confirm dialog and flash toast). pint, tsc --noEmit, and eslint all clean.
<!-- SECTION:FINAL_SUMMARY:END -->
