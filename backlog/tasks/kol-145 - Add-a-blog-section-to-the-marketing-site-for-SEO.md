---
id: KOL-145
title: Add a blog section to the marketing site for SEO
status: Done
assignee:
  - '@jorgejavierleon'
created_date: '2026-10-05 09:36'
updated_date: '2026-10-05 10:23'
labels: []
dependencies: []
ordinal: 167000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Create a blog accessible from the public landing page so we can publish SEO-targeted articles. Needs a listing page, an individual post view, and a link from the landing page nav/footer. Content authoring format (Markdown files vs DB-backed posts) is left to the implementer to decide based on existing conventions.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A /blog route lists published posts with title, excerpt, and publish date
- [x] #2 A /blog/{slug} route renders a single post's full content with appropriate SEO meta tags (title, description, OpenGraph)
- [x] #3 The landing page has a visible link to /blog (nav and/or footer)
- [x] #4 Blog pages are publicly accessible without authentication
- [x] #5 Blog listing and post pages are responsive and follow the landing page's existing visual style
- [x] #6 Sitemap or robots considerations are documented if the project has an existing sitemap mechanism
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Content format decision: no DB table. This app has no CMS; the closest convention is `App\Support\*` plain PHP value objects (Folio, ReportPeriod, etc.) plus hand-authored React/Tailwind pages (landing.tsx is itself a static JSX page, not DB-driven). Follow that: a static `BlogPost`/`BlogPosts` registry in `app/Support/Blog/`, and each post's body is its own Inertia/React page under `resources/js/pages/blog/posts/{slug}.tsx`, matching landing's hand-coded style. Avoids adding a Markdown dependency (league/commonmark is only a transitive dep today) or a DB migration for a 1-2 post blog with no admin UI in scope.
2. Backend: `App\Support\Blog\BlogPost` (readonly value object: slug, title, excerpt, metaDescription, publishedAt) and `App\Support\Blog\BlogPosts::all()/find()`. `App\Http\Controllers\BlogController@index` renders `blog/index` with post summaries; `@show` looks up by slug, 404s via `abort(404)` if missing, renders `blog/posts/{slug}` with post meta.
3. Routes: public `GET /blog` (blog.index) and `GET /blog/{slug}` (blog.show) in routes/web.php, outside the auth group (AC #4). Regenerate Wayfinder.
4. Frontend: `resources/js/components/site-footer.tsx` (extracted, reused by landing + blog pages to avoid triplicating identical markup). `resources/js/components/blog/post-layout.tsx` shared chrome for post pages (Head with title/meta description/OG tags, simple header, SiteFooter). `resources/js/pages/blog/index.tsx` listing cards (title, excerpt, date) linking to blog.show. Add a "Blog" link to landing.tsx's desktop nav, mobile nav, and footer (AC #3).
5. First post (KOL-145.1): write `app/Support/Blog/BlogPosts.php` entry + `resources/js/pages/blog/posts/beneficios-de-conectar-un-mcp-a-tu-sistema-de-rrhh.tsx` in Spanish about MCP + Kolvi's existing MCP server (KOL-126/127/129), with an internal link back to the landing page.
6. Sitemap/robots (AC #6): no sitemap mechanism exists in the project today (checked public/robots.txt — allow-all, no sitemap.xml, no route). Document this finding in the task notes/final summary rather than building one (out of scope — AC only asks to document if a mechanism exists).
7. Tests: tests/Feature/BlogTest.php mirroring LandingPageTest.php style — index renders `blog/index` with the known post in props, show renders the post's own component with meta props, unknown slug 404s. Add/extend a landing test only if a reliable server-rendered assertion is possible (nav link is client-rendered JSX, not SSR-asserted today); otherwise verify the nav link visually in the browser.
8. Run pint --dirty, the new Pest file, tsc --noEmit, then the full affected suite; verify /blog and /blog/{slug} in the browser (dev server) for visual/responsive AC #5.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verified in browser (dev server, desktop + 390px mobile viewport): /blog lists the post card with date/title/excerpt; /blog/{slug} renders with the article typography, OG/meta tags present in page source; landing desktop nav + mobile Sheet menu both show a working Blog link; AdminLayout bleed-through bug found and fixed (app.tsx's layout resolver defaulted unmatched page names to AdminLayout, which wrapped blog pages in the authenticated sidebar — added a 'blog' case alongside 'landing' returning null).

Sitemap/robots (AC6): no sitemap mechanism exists in the project — public/robots.txt is a plain allow-all with no sitemap reference, and there is no sitemap route/controller anywhere in the codebase. Nothing to wire up; documenting that finding satisfies the AC.

sa test --compact --filter='BlogTest|LandingPageTest' passes (9/9); npm run types:check and vendor/bin/pint --dirty --format agent both clean. Full, unfiltered suite deliberately not run yet per established preference (run only after user review).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a public /blog (listing) and /blog/{slug} (post) section, reachable from the landing page's desktop nav and mobile nav menu. Content has no DB table: a static App\Support\Blog\BlogPost(s) registry (matching this app's existing plain-value-object convention, e.g. Folio/ReportPeriod) drives the listing, and each post is its own hand-coded Inertia/React page (matching how the landing page itself is static JSX), keeping full design control and SEO meta (title, description, OpenGraph) per post via a shared BlogPostLayout. Extracted SiteFooter to avoid tripling identical markup. Fixed a real bug found during browser verification: app.tsx's Inertia layout resolver defaulted any unmatched page name to the authenticated AdminLayout, which wrapped the new public blog pages in the logged-in sidebar. Verified with tests/Feature/BlogTest.php (index/show/404) + tests/Feature/LandingPageTest.php, and visually in a real browser at desktop and 390px mobile widths.
<!-- SECTION:FINAL_SUMMARY:END -->
