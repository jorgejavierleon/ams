# Manual QA checklist

Work that shipped without a local browser check, waiting for a human to exercise
it in the UI. Automated tests are not listed here — if a behaviour can be
asserted in Pest it belongs in a test, not on this page. What lands here is the
part only eyes can confirm: rendering, wording, layout, the feel of a form.

**How this file is maintained.** The `implement-ticket` skill appends an entry
whenever a task is finished but its QA is deferred (see Phase 4.5 of
`.claude/skills/implement-ticket/SKILL.md`). Entries are added at the top of
_Pending_, newest first.

**How to work through it.** Start the app with `composer run dev`, tick each box
as you go. When every box of an entry is ticked, move the entry to _Verified_
with the date. If something is wrong, note it under the entry and tell Claude —
the branch name and commit are recorded so the fix can start from the right
place.

---

## Decisions to confirm

Not browser QA — no code shipped for these. They are design or spec choices made
without you in the room, recorded so you can agree or overrule them before the
task is implemented. Once a decision here is settled, delete it; once the task
ships, its real browser checks land under _Pending_ like anything else.

### KOL-38 — Early arrival and calculated overtime

- **Changed on:** 2026-08-07 (ticket + PRD only, uncommitted — no app change)
- **Where:** `backlog/tasks/kol-38 - …md` and `docs/PRD_Overtime_Module_Kolvi_EN.md`
  §4, §7.2, §8, §10
- **Trigger:** you asked why an early arrival earned no overtime, and noted that
  Buk/Talana configure this. They do — GeoVictoria's `AttendanceBook` returns
  *horas extra (antes/después de turno)* separately (`docs/prd-reports.md:60`).

- [ ] **The split is right.** OHC becomes `post-shift excess + (pre-shift excess
      if the tenant counts it)`, where pre-shift is `shift start − first mark`.
      The old ticket had only `last mark − shift end`; the current code has
      `worked span − scheduled duration`, which counts early arrival silently.
- [ ] **The default is right.** New organizations do **not** count early arrival.
      The conservative reading of Art. 32 (excess hours need employer knowledge)
      wins over the DT reality criterion by default; tenants who need the
      permissive reading turn it on. Overruling this means flipping one default.
- [ ] **Storing both excesses unconditionally is worth the column.** The point is
      that enabling the setting later is config, not a recalculation of history —
      and that a recurring pre-shift excess stays visible to KOL-40 as a probable
      shift-definition error. Cost is two columns nobody reads on day one.
- [ ] **The toggle stays in tenant settings rather than going per-shift.**
      You raised per-shift; we settled on no, for now. `Shift` already carries
      per-shift policy (`tolerance_in`, `tolerance_out`, `work_on_holidays`) so
      it would not be foreign, but no tenant has asked, and because both
      excesses are stored unconditionally the move is later a recompute, not a
      migration. KOL-38 AC #14 requires the policy be resolved behind a single
      seam so that move stays cheap. Promote it only when real usage shows the
      same override set on the same shifts repeatedly.
- [ ] **The mixed-company case is answered by KOL-55, not by configuration.**
      `MIN(OHR, OHA, OHC)` means an excluded pre-shift excess is not merely
      unpaid by default — it is structurally unpayable, since MIN caps at OHC.
      KOL-55 lets a reviewer include a single day's pre-shift excess with a
      written reason, which keeps the MIN invariant intact (OHC is amended, not
      bypassed). Check you agree that a per-day human act is the right escape
      hatch, rather than a broader configuration axis.
- [ ] **PRD §7.2 now contradicts nothing else.** It previously stated the
      shift-end formula as absolute; check the rewrite reads as intended, since
      every downstream overtime task is written against it.

---

## Pending

### KOL-37 — Overtime policy per tenant

- **Branch:** `feature/kol-37-overtime-policy-settings` (merged to `master`)
- **Deferred on:** 2026-08-07
- **Where:** `Configuración general` (`/organization-settings`), as an admin
- **Automated coverage:** defaults, each mode round-tripping through the
  settings service, cache invalidation scoped per tenant, tenant isolation,
  validation rejection — all in `tests/Feature/OrganizationSettingsTest.php`

- [ ] The page shows a third section, **Horas extra**, below Notificaciones and
      Documentos, with a timer icon in its header.
- [ ] On an organization that has never saved settings, the section opens with:
      modo **Revisión posterior**, compensación **Pago en remuneraciones**,
      umbral semanal **10**, días hacia atrás **7**, and **Exigir pacto escrito**
      switched off.
- [ ] Both selects are in Spanish and offer every option — mode:
      Autorización previa / Revisión posterior / Combinado; compensation:
      Pago en remuneraciones / Días de descanso.
- [ ] The two number fields accept decimals where it makes sense: the umbral
      steps by 0.5 (14.5 is accepted and comes back as 14.5 after saving); the
      días field steps by 1.
- [ ] Changing every field in the section and pressing **Guardar** shows the
      success toast, keeps the scroll position, and the new values are still
      there after a full page reload.
- [ ] The four fields sit two-per-row on a wide window and stack to one column
      on a narrow one, with the pacto switch as a full-width row underneath.
- [ ] Hint text under each field is legible and not clipped in dark mode.
- [ ] Clearing a number field and saving shows a validation message under that
      field rather than silently saving a zero or a blank.
- [ ] The Notificaciones and Documentos toggles still save correctly alongside
      the new fields — the form submits as a whole, so a regression there would
      show up as settings reverting after reload.
- [ ] No console errors on load or on save (check `browser-logs` or devtools).

---

## Verified

_Nothing verified yet._
