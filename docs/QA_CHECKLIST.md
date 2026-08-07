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
