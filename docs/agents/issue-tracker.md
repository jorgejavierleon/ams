# Issue tracker: Backlog.md

Issues and specs for this repo live as tasks in `backlog/`, managed via the Backlog.md CLI (task prefix `KOL`, e.g. `KOL-93`). GitHub Issues #2–#77 are a closed, read-only archive — do not use `gh issue` on this repo.

## Conventions

- **Create a task**: `backlog task create "<title>" --desc "<description>" --ac "<criterion>"` (repeat `--ac` for more acceptance criteria).
- **Read a task**: `backlog task <id> --plain` (or `--json` for machine-readable output).
- **List tasks**: `backlog task list --plain`, filtered with `--status`, `--assignee`, `--parent`, `--labels`, `--milestone`, or `--search "<query>"`. Never run bare `backlog board` — it opens an interactive TUI that hangs a non-interactive shell.
- **Comment on a task**: `backlog task edit <id> --comment "<text>"`.
- **Update status**: `backlog task edit <id> --status "<status>"`. Statuses: `Draft`, `To Do`, `In Progress`, `In Review`, `Done`.
- **Never edit task/draft/document/decision markdown files directly** — always go through the `backlog` CLI so metadata and relationships stay consistent.

Commits are prefixed `KOL-<N>` (not `#<N>`, which refers to the archived GitHub issues). Branches are `feature/kol-<N>-<slug>`.

This repo already has its own `implement-ticket` skill governing the full dev lifecycle (task selection → branch → implementation → review gate → commit/merge/push). These mattpocock skills complement it for planning (`/to-spec`, `/to-tickets`) rather than replacing task execution.

## Pull requests as a triage surface

Not applicable — this repo doesn't triage external PRs through Backlog.md.

## When a skill says "publish to the issue tracker"

`backlog task create "<title>" --desc "<description>"`, adding `--ac` for acceptance criteria and `--milestone`/`--parent`/`--labels` as appropriate.

## When a skill says "fetch the relevant ticket"

`backlog task <id> --plain`. The user will normally pass the task ID (e.g. `KOL-93`) directly.

## Wayfinding operations

Used by `/wayfinder`. The **map** is a parent task with **child** tasks as tickets.

- **Map**: `backlog task create "<effort> map" --desc "<Notes / Decisions-so-far / Fog body>"`.
- **Child ticket**: `backlog task create "<question>" --parent <map-id> --type <research|prototype|task>`. List a map's children with `backlog task list --parent <map-id>`.
- **Blocking**: native dependencies via `--depends-on <taskIds>` at creation, or `backlog task edit <id> --depends-on <taskIds>` after. A ticket is unblocked when every dependency is `Done`.
- **Frontier query**: `backlog task list --parent <map-id> --ready` — lists unblocked tasks with all dependencies completed; first by ID wins.
- **Claim**: `backlog task edit <id> --status "In Progress" -a @me`.
- **Resolve**: `backlog task edit <id> --comment "<answer>" --status Done`, then append a context pointer (gist + link) to the map's Decisions-so-far via `backlog task edit <map-id> --append-notes "..."`.
