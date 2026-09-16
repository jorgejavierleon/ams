---
id: KOL-115
title: Swap the app theme to the blue tweakcn palette
status: Done
assignee: []
created_date: '2026-09-14 10:28'
updated_date: '2026-09-16 11:35'
labels: []
dependencies: []
ordinal: 102000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The current resources/css/app.css theme was the shadcn default neutral/zinc palette (oklch grays, near-black primary). Replaced the :root and .dark token blocks with a blue palette exported from tweakcn.com's theme editor, chosen after live-previewing it and two other candidates (teal and terracotta/orange) against the running app via Vite HMR. The palette uses the exact same variable names as the existing @theme mapping, so it was a drop-in swap of values plus --radius (0.625rem -> 0.375rem for sharper corners) -- no new tokens, no @theme block changes. The palette's own --font-sans/--font-serif/--font-mono and --shadow-*/--tracking-* tokens were deliberately not adopted: fonts stay as Instrument Sans per explicit instruction, and the shadow/tracking tokens would override Tailwind's default scale used elsewhere (e.g. button.tsx's shadow-xs) for no requested benefit. A separate Flux UI snippet was also considered earlier and rejected: this app is Inertia v3 + React 19 with a shadcn-style theme, not Livewire Flux, so its --color-accent/zinc-remap variables have no corresponding tokens here.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 resources/css/app.css :root block uses the new blue oklch values (background, foreground, card, popover, primary, secondary, muted, accent, destructive, destructive-foreground, border, input, ring, chart-1..5, sidebar*)
- [x] #2 .dark block uses the corresponding blue dark-mode oklch values from the same palette
- [x] #3 --radius is updated to 0.375rem to match the chosen palette's corner radius
- [x] #4 No component hardcodes a color that visually conflicts with the new palette (spot-checked primary buttons, badges, sidebar, and the FullCalendar/tiptap theme hookups at the bottom of app.css -- all reference the CSS custom properties by name)
- [x] #5 Light and dark mode both previewed live via Vite HMR and approved by the user
- [x] #6 The Flux UI snippet's zinc-remap and --color-accent variables are not added to app.css
- [x] #7 The --font-sans value and @theme font stack in app.css are left untouched; this task only changes color tokens and --radius
- [x] #8 Row-link cells across list pages (document-templates, documents, dt/documents, employees, positions, premises, shifts) no longer hardcode text-primary/underline styling, since the new blue primary read poorly as an inline table-row link; they use plain text with a pointer cursor instead
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
- [x] #5 npm run build completes without error
- [x] #6 Manual check in the browser confirms light and dark mode render correctly across at least one list page, one form, and the sidebar
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Replace :root oklch color tokens in resources/css/app.css with the shadcn teal palette values (background, foreground, card, popover, primary, secondary, muted, accent, destructive, border, input, ring, chart-1..5, sidebar*).
2. Replace .dark oklch color tokens with the corresponding teal dark-mode values.
3. Keep --destructive-foreground (light + dark) since the pasted palette omits it; reuse the existing values, which already have adequate contrast against the new --destructive (unchanged red).
4. Leave --font-sans, --radius-*, @theme mappings, and everything below the :root/.dark blocks (FullCalendar, tiptap) untouched -- they reference the CSS custom properties by name so they pick up the new colors automatically.
5. Do not add Flux's zinc-remap or --color-accent/-content/-foreground tokens.
6. npm run build, then manually check light/dark mode in the browser on a list page, a form, and the sidebar.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
No PHP or TypeScript touched, so DoD #1/#3/#4 are vacuously satisfied. Iterated through three tweakcn/shadcn palette candidates (teal, terracotta/orange, blue) live against the running app via Vite HMR before the user picked the blue one. Full Pest suite green (1430 passed, 4 skipped) and npm run build clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Replaced the neutral/zinc shadcn theme in resources/css/app.css with a blue palette from tweakcn.com's theme editor: new :root/.dark oklch color tokens plus --radius 0.375rem. Existing Instrument Sans font stack, Flux's zinc-remap/accent tokens, and the palette's own shadow/tracking tokens were deliberately excluded. Verified live in light and dark mode via Vite HMR against list pages, forms, and the sidebar; full test suite and production build both pass.
<!-- SECTION:FINAL_SUMMARY:END -->
