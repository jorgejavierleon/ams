---
name: implement-ticket
description: "Use this skill when the user asks to pick a task, work on the next ticket, implement a Backlog.md task, or start a new feature from the backlog. Triggers: 'pick next ticket', 'work on KOL-N', 'implement next task', 'start ticket', 'next issue', 'new task', or any request to begin work on a backlog item. This skill encodes the full AI-assisted development workflow: task selection → branch → implementation → review gate → commit/merge/push."
license: MIT
metadata:
  author: jorgejavierleon
---

# Implement Ticket Workflow

This skill defines the full workflow for AI-assisted task implementation in this project.
Follow every phase in order. Do not skip phases or reorder them.

Tasks are managed with [Backlog.md](https://github.com/MrLesk/Backlog.md) in `backlog/`,
as plain markdown committed with the code. There is no external issue tracker — **never
use `gh issue`**. GitHub Issues #2–#77 are a closed, read-only archive from before the
switch.

## CLI ground rules

- **Always pass `--plain` to `task list` and `task view`.** Without it they open an
  interactive TUI that will hang a non-interactive shell.
- **There is no `--json` flag** in the installed version (1.48.0). Parse `--plain` output.
- **Never run bare `backlog board`** — also a TUI. Use `backlog board export <file> --force`
  when a board snapshot is needed. Its path is resolved **project-relative**, so an
  absolute path like `/tmp/x.md` creates `<project>/tmp/x.md`. Pass a bare filename.
- Task IDs are `KOL-<n>` (uppercase) in commands and frontmatter; filenames use `kol-<n>`.

---

## Phase 0 — Creating a Task

Only when the user asks for a new task rather than work on an existing one.

```bash
backlog task create "Short imperative title" \
  -l <label> \
  -d "Context: why this exists and what problem it solves." \
  --ac "First verifiable criterion" \
  --ac "Second verifiable criterion"
```

Useful flags: `--dep KOL-3` (dependencies), `--draft` (not ready to start), `-l` (labels),
`--ref <url-or-path>` (link a source), `--priority`, `-m` (milestone).

Definition-of-Done items are applied automatically from `backlog/config.yml` — do not
restate them per task.

Acceptance criteria must be concrete and checkable. A task whose criteria are vague
cannot be verified as done — push back and refine rather than accepting "improve X".

**Every task's description must include a `## User stories for manual testing (Gherkin)`
section** — one or more concrete `Given/When/Then` scenarios describing something a human
can actually do and observe (open a screen, click something, see a result), not just
backend acceptance criteria. KOL-60 already carries this pattern; use it as the template.

**Write the Gherkin scenarios before finalizing the task, and let them drive the scope
check.** If no real `Given/When/Then` can be written — nothing a person could act out —
that is the signal this task is a **horizontal slice** (a data-model layer, a service with
no caller, an API with no UI yet) rather than a **vertical slice** (a thin end-to-end path
a user can see). When decomposing an epic into multiple tasks:
- Prefer a thin walking-skeleton-first ticket that reaches end-to-end (even with a rough
  or hardcoded middle) over splitting into "all the data tickets, then all the service
  tickets, then the UI last."
- A purely infrastructural ticket (no UI, no route) is sometimes unavoidable — but treat it
  as a smell, name it explicitly as infrastructure for a specific upcoming vertical-slice
  ticket, and keep the horizontal chain as short as possible before something becomes
  human-testable.
- If in doubt, ask the user whether to keep a horizontal decomposition or re-plan around a
  vertical slice, rather than silently proceeding.

**Prefer a normal task over `--draft`.** Drafts land in `backlog/drafts/` with a separate
`DRAFT-<n>` ID and are **invisible to `backlog task list`** — easy to forget. For an
under-specified item, create a normal task whose first acceptance criterion is to define
the real ones. Use `--draft` only for genuinely speculative ideas, and promote with
`backlog draft promote DRAFT-<n>` (which renumbers it into the KOL sequence).

Creating a task does not start implementation. Stop here unless the user asked for both.

---

## Phase 1 — Task Selection

**If the user named a specific task:**
```bash
backlog task view KOL-<N> --plain
```

**If the user said "next ticket" or similar (no ID given):**
```bash
backlog task list -s "To Do" --plain
```
Pick the **lowest-numbered** task in `To Do`. Skip anything in `Draft` (criteria not yet
defined) and anything already `In Progress`.

After selecting, read the whole task — Description, Acceptance Criteria, and any
Implementation Plan or Notes. Understand all of them before proceeding.

**Check dependencies.** Read the `dependencies` frontmatter field. For each ID listed,
confirm it is `Done`:
```bash
backlog task view KOL-<dep> --plain
```
If any dependency is not `Done`, stop and tell the user which task must be completed first.

If the task is a `Draft` or its acceptance criteria are empty, stop and work with the user
to define them before writing any code.

---

## Phase 1.5 — No-Code Check

After verifying all acceptance criteria, determine whether any code changes are actually needed.

**If every acceptance criterion is already satisfied** (the starter kit, prior work, or
existing code already covers all of them):

1. Do NOT create a branch.
2. Tick every criterion and close the task:
```bash
backlog task edit KOL-<N> --check-ac 1 --check-ac 2 \
  --notes "All acceptance criteria already satisfied by existing code. No changes required." \
  -s Done
```
3. Announce to the user that the task is done and ask if they want to pick the next one.
4. **Stop here — do not proceed to Phase 2.**

Only continue to Phase 2 if actual code changes are needed.

---

## Phase 2 — Branch Creation

```bash
git checkout master
git pull origin master
git checkout -b feature/kol-<N>-<slug>
backlog task edit KOL-<N> -s "In Progress"
```

Example: `feature/kol-12-spanish-i18n`

Record the approach on the task so it survives a context reset:
```bash
backlog task edit KOL-<N> --plan "1. …  2. …  3. …"
```

Announce the branch name to the user.

---

## Phase 3 — Implementation

### Before writing any code
1. Re-read the task's **Acceptance Criteria** — every one must be satisfied
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
sa test --compact
```

This project runs under Laravel Sail — bare `php artisan test` fails because the DB host
is `mysql`. Use the `sa` alias (or `./vendor/bin/sail artisan`).

All tests must pass before proceeding to Phase 4. Fix failures before continuing.

Record anything discovered along the way that the task should carry forward:
```bash
backlog task edit KOL-<N> --notes "…"
```

---

## Phase 3.5 — Documentation (optional)

Read `docs/architecture.md` before deciding. Update it **only** if the task introduced something a future developer or AI agent couldn't infer from reading the code:

- A non-obvious architectural decision (e.g. why a design was chosen over alternatives)
- A naming convention that applies project-wide
- A constraint or invariant that must not be broken
- A cross-cutting integration (package choice, auth flow, tenancy mechanism)

**Do not document:**
- What a class or method does (the code already says that)
- Standard Laravel/Inertia patterns followed without deviation
- Implementation details of a single feature

Keep entries short. One paragraph or a small table is enough. If nothing in the task meets the bar above, skip this phase entirely.

If the work revealed something that changes a *different* task's scope, update that task
now with `backlog task edit` while it's fresh.

---

## Phase 4 — Verification Gate (WAIT FOR USER)

Before committing anything:

1. Move the task to review: `backlog task edit KOL-<N> -s "In Review"`
2. If there is UI work, describe exactly what the user should verify in the browser
3. Use `browser-logs` to check for console errors if the app is running
4. Tell the user explicitly:

> "Implementation complete. Please review the changes — run `composer run dev` if you need the dev server. Let me know when you're happy and I'll commit, merge, and push."

**Do not proceed to Phase 5 until the user explicitly confirms** (e.g. "looks good", "merge it", "ship it").

---

## Phase 4.5 — Deferred QA (only when the user cannot test now)

If the user says they cannot check the work in a browser right now — "I'm not at my
machine", "can't QA this", "I'll test it later", or anything to that effect — do **not**
leave the branch hanging. Log what needs eyes on it, then ship.

1. **Append an entry to `docs/QA_CHECKLIST.md`**, at the top of the _Pending_ section
   (newest first), following the shape of the entries already there:

```markdown
### KOL-<N> — <task title, short>

- **Branch:** `feature/kol-<N>-<slug>` (merged to `master`)
- **Deferred on:** <YYYY-MM-DD>
- **Where:** <screen name in Spanish> (`/route`), as a <role>
- **Automated coverage:** <one line: what the Pest tests already prove, and where>

- [ ] <one concrete, observable check>
- [ ] <…>
```

2. **Write checks a human can actually perform.** Each box names a screen, an action and
   an expected result specific enough that "it looks fine" is not a valid answer. Cover
   the things tests cannot: rendering and layout at wide and narrow widths, Spanish
   wording, dark mode, empty and error states, the toast after saving, values surviving a
   reload, and console errors.

3. **Do not restate what Pest already asserts.** If a behaviour is covered by a test, the
   `Automated coverage` line mentions it and no box is spent on it. A checklist padded
   with things the suite already guarantees stops being read.

4. **Include the regression edge.** When the change extends an existing screen or form,
   add a box for the pre-existing behaviour that could break — a form that submits as a
   whole is the usual case.

5. If the task had no UI at all, skip the file entirely and say so; a backend-only change
   has nothing for a human to look at that the tests don't already cover.

6. **Then run Phase 5 in full** — close the task, commit, merge, push. Include
   `docs/QA_CHECKLIST.md` in the same commit. Tell the user the work is on `master` and
   that the QA entry is waiting for them.

When the user later reports back that an entry passed, move it from _Pending_ to
_Verified_ with the date. If something failed, keep the entry where it is and open the
fix from the branch recorded in it.

---

## Phase 5 — Commit, Merge, Push (only after user confirms)

Close the task **on the feature branch**, in the same commit as the code, so the merge
carries both and the task file at any commit reflects the true state of the tree.

1. Tick every acceptance criterion and Definition-of-Done item, then close:
```bash
backlog task edit KOL-<N> \
  --check-ac 1 --check-ac 2 --check-ac 3 \
  --check-dod 1 --check-dod 2 \
  --final-summary "<what shipped, one or two sentences>" \
  -s Done
```

2. Commit, merge, push:
```bash
# Commit all changes on the feature branch, including the task file
git add <relevant files> backlog/tasks/
git commit -m "KOL-<N> <short description>"

# Merge to master
git checkout master
git merge feature/kol-<N>-<slug> --no-ff -m "KOL-<N> Merge feature/kol-<N>-<slug>"

# Push
git push origin master
```

Commit messages are prefixed `KOL-<N>`, not `#<N>`. The `#N` form refers to the archived
GitHub issues and means something different.

Announce to the user: the branch, commit hash, and that the task is closed. Ask if they want to pick the next task.

---

## Quick Reference

| Command | Purpose |
|---|---|
| `backlog task list -s "To Do" --plain` | Find the next task |
| `backlog task list --plain` | All tasks, grouped by status |
| `backlog task view KOL-<N> --plain` | Read task details |
| `backlog task create "<title>" --ac "…"` | Create a task |
| `backlog task edit KOL-<N> -s "In Progress"` | Change status |
| `backlog task edit KOL-<N> --check-ac <i>` | Tick an acceptance criterion |
| `backlog search "<query>"` | Fuzzy search tasks, docs, decisions |
| `backlog board export BOARD.md --force` | Write a markdown kanban snapshot (project-relative path) |
| `backlog draft list --plain` | List drafts (hidden from `task list`) |
| `backlog draft promote DRAFT-<n>` | Turn a draft into a numbered KOL task |
| `docs/QA_CHECKLIST.md` | Manual checks waiting on the user (Phase 4.5) |
| `sa test --compact` | Run tests |
| `vendor/bin/pint --dirty --format agent` | Fix PHP code style |
| `sa route:list --except-vendor` | Inspect routes |
| `sa tinker --execute '...'` | Debug PHP in app context |

Statuses are `Draft`, `To Do`, `In Progress`, `In Review`, `Done` (see `backlog/config.yml`).
