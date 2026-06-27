---
name: implement-ticket
description: "Use this skill when the user asks to pick a ticket, work on the next issue, implement a GitHub issue, or start a new feature from the backlog. Triggers: 'pick next ticket', 'work on #N', 'implement next issue', 'start ticket', 'next issue', or any request to begin work on a migration issue. This skill encodes the full AI-assisted development workflow: ticket selection → branch → implementation → review gate → commit/merge/push."
license: MIT
metadata:
  author: jorgejavierleon
---

# Implement Ticket Workflow

This skill defines the full workflow for AI-assisted ticket implementation in the AMS migration project.
Follow every phase in order. Do not skip phases or reorder them.

---

## Phase 1 — Ticket Selection

**If the user named a specific issue number:**
```bash
gh issue view <N> --repo jorgejavierleon/ams
```

**If the user said "next ticket" or similar (no number given):**
```bash
gh issue list --repo jorgejavierleon/ams --label migration --state open --limit 50 --json number,title,labels
```
Pick the **lowest-numbered open issue** that has no `in-progress` label. Issues are ordered M1→M4, so #2 comes before #3, etc.

After selecting, read the full issue body — it contains Context, Acceptance Criteria, Technical Notes, Old App Reference, and Dependencies. Understand all of them before proceeding.

Check the Dependencies section. If any dependency issues are still open, stop and tell the user which issue must be completed first.

---

## Phase 1.5 — No-Code Check

After verifying all acceptance criteria, determine whether any code changes are actually needed.

**If every acceptance criterion is already satisfied** (the starter kit, prior work, or existing code already covers all checkboxes):

1. Do NOT create a branch.
2. Close the issue directly on GitHub and move it to Done:
```bash
gh issue close <N> --repo jorgejavierleon/ams --comment "All acceptance criteria already satisfied by existing code. No changes required."
```
3. Announce to the user that the ticket is done and ask if they want to pick the next one.
4. **Stop here — do not proceed to Phase 2.**

Only continue to Phase 2 if actual code changes are needed.

---

## Phase 2 — Branch Creation

Create a branch following this exact naming convention:
```
feature/<issue-number>-<short-slug>
```

Example: `feature/3-spatie-permissions`

```bash
git checkout master
git pull origin master
git checkout -b feature/<N>-<slug>
```

Announce the branch name to the user.

---

## Phase 3 — Implementation

### Before writing any code
1. Re-read the ticket's **Acceptance Criteria** — every checkbox must be satisfied
2. Check `../ams-filament` for existing business logic to reuse (models, managers, services, observers)
3. Run `search-docs` for any framework API you're about to use

### Activate relevant skills
Based on the work involved, activate:
- `inertia-react-development` — for any React pages, forms, or Inertia patterns
- `pest-testing` — whenever writing or modifying tests
- `laravel-best-practices` — for controllers, models, queries
- `fortify-development` — for anything auth-related
- `tailwindcss-development` — for Tailwind v4 styling
- `wayfinder-development` — when using typed route helpers

### Implementation rules
- Every PHP change needs a Pest test
- Reuse existing components from `resources/js/components/` before writing new ones
- Use Wayfinder route helpers (`@/actions/` or `@/routes/`) for all route references in TypeScript
- Keep multi-tenancy: all models behind `BelongsToOrganization` must scope to the current org
- Never reimplement `MarkManager`, `LeaveManager`, `WorkdayCalculator`, or any Observer — import them

### After all PHP changes
```bash
vendor/bin/pint --dirty --format agent
```

### Run tests
```bash
php artisan test --compact
```

All tests must pass before proceeding to Phase 4. Fix failures before continuing.

---

## Phase 3.5 — Documentation (optional)

Read `docs/architecture.md` before deciding. Update it **only** if the ticket introduced something a future developer or AI agent couldn't infer from reading the code:

- A non-obvious architectural decision (e.g. why a design was chosen over alternatives)
- A naming convention that applies project-wide
- A constraint or invariant that must not be broken
- A cross-cutting integration (package choice, auth flow, tenancy mechanism)

**Do not document:**
- What a class or method does (the code already says that)
- Standard Laravel/Inertia patterns followed without deviation
- Implementation details of a single feature

Keep entries short. One paragraph or a small table is enough. If nothing in the ticket meets the bar above, skip this phase entirely.

---

## Phase 4 — Verification Gate (WAIT FOR USER)

Before committing anything:

1. If there is UI work, describe exactly what the user should verify in the browser
2. Use `browser-logs` to check for console errors if the app is running
3. Tell the user explicitly:

> "Implementation complete. Please review the changes — run `composer run dev` if you need the dev server. Let me know when you're happy and I'll commit, merge, and push."

**Do not proceed to Phase 5 until the user explicitly confirms** (e.g. "looks good", "merge it", "ship it").

---

## Phase 5 — Commit, Merge, Push (only after user confirms)

```bash
# Commit all changes on the feature branch
git add <relevant files>
git commit -m "#<N> <short description>"

# Merge to master
git checkout master
git merge feature/<N>-<slug> --no-ff -m "#<N> Merge feature/<N>-<slug>"

# Push
git push origin master

# Close the issue
gh issue close <N> --repo jorgejavierleon/ams --comment "Implemented and merged to master."
```

Announce to the user: the branch, commit hash, and that the issue is closed. Ask if they want to pick the next ticket.

---

## Quick Reference

| Command | Purpose |
|---|---|
| `gh issue list --repo jorgejavierleon/ams --label migration --state open` | Find next ticket |
| `gh issue view <N> --repo jorgejavierleon/ams` | Read ticket details |
| `sa test --compact` | Run tests |
| `vendor/bin/pint --dirty --format agent` | Fix PHP code style |
| `sa route:list --except-vendor` | Inspect routes |
| `sa tinker --execute '...'` | Debug PHP in app context |
