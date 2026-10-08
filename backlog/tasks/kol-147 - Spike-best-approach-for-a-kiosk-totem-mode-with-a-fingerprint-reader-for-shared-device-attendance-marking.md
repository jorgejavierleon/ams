---
id: KOL-147
title: >-
  Spike: best approach for a kiosk/totem mode with a fingerprint reader for
  shared-device attendance marking
status: In Review
assignee: []
created_date: '2026-10-06 12:01'
updated_date: '2026-10-06 23:10'
labels:
  - spike
  - attendance
dependencies: []
references:
  - docs/prd-mobile-app.md
type: spike
ordinal: 173000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Investigate the best way to support a totem/kiosk mode: a single shared device (tablet or dedicated terminal) at a premise entrance, with a fingerprint reader, that multiple employees use to mark attendance — as opposed to today's per-employee browser/phone marking.

Why this is its own spike: docs/prd-mobile-app.md explicitly lists this as a non-goal of the mobile app PRD (section 1.2): "Kiosk / shared-device mode (one tablet at the premise entrance, many employees). Different auth model; separate PRD if wanted" and "Biometric identification of the employee (fingerprint matched server-side)... Device biometric unlock is in scope" (identification is not). This spike is that separate investigation.

This is research only. Do not build anything from this task — its output is a written finding and a recommendation, not code.

What this spike should settle:
- What fingerprint-reader hardware options exist for a kiosk (USB fingerprint scanners paired with a tablet/PC, Android tablets with a built-in sensor, dedicated biometric time-clock terminals) and their rough cost and integration complexity.
- What identification/auth model a shared device actually needs. Today's Sanctum device-token model (KOL-5 through KOL-8) issues one token per employee device and assumes the device belongs to whoever is marking; a totem flips that — one device, many people, identified per punch rather than per session.
- The Resolución 38 Art. 7g constraint already flagged in the mobile PRD: fiscalization requires two identification alternatives, one of them non-biometric. A totem design needs a non-biometric fallback (PIN, badge/card, employee ID entry) alongside the fingerprint reader, not fingerprint alone — this spike should address that requirement concretely, not just restate it.
- Whether a totem punch can reuse the existing MarkManager / Geofence / signed-receipt pipeline unchanged. Note: a premise's coordinates are fixed and a totem never moves, so the geofence verdict is close to a formality for this entry channel specifically — worth deciding whether it is still computed and stored for consistency, or treated differently.
- A brief check of what Talana, Buk and GeoVictoria offer here (biometric terminal integrations, kiosk tablet apps), if anything — a light market check, not the deep per-competitor research KOL-146 is already doing for rotating shifts.
- A recommendation: dedicated hardware terminal vs. a kiosk-mode app on a commodity tablet with a USB or built-in fingerprint reader, and a rough scope estimate for a first version.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Fingerprint-reader hardware options for a shared kiosk device are listed with rough cost and integration complexity
- [x] #2 The identification/auth model needed for a one-device-many-employees totem is worked out, including how it differs from today's per-employee Sanctum device-token model
- [x] #3 The Resolución 38 Art. 7g non-biometric-alternative requirement is addressed concretely in the proposed design, not just restated
- [x] #4 Whether totem marks can reuse MarkManager/Geofence/signed-receipt as-is, or need changes, is determined and documented
- [x] #5 A brief check of what Talana, Buk and GeoVictoria offer for kiosk/biometric attendance marking is recorded
- [x] #6 A recommendation (hardware approach plus a rough v1 scope) is written down; if viable, candidate follow-up tasks are named but not created or implemented as part of this spike
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [ ] #1 vendor/bin/pint --dirty --format agent reports clean
- [ ] #2 sa test --compact passes
- [ ] #3 npm run types:check passes when TypeScript touched
- [ ] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Read current MarkManager/MarkObserver/MarkController/Geofence/Folio code (built since the PRD baseline) to determine whether the mark pipeline is channel-agnostic. 2. Read Resolucion 38 Art. 7 (a-g) for the non-biometric-alternative requirement text. 3. Research fingerprint-reader/kiosk-terminal hardware options and rough cost/integration complexity (dedicated biometric terminal vs USB reader + tablet vs built-in sensor). 4. Delegate a brief public-source check of Talana/Buk/GeoVictoria kiosk/biometric offerings to a background agent, output to docs/research/. 5. Work out the auth/identification model a shared totem needs vs today's per-employee Sanctum device-token model. 6. Write a concrete Art. 7g-compliant design (fingerprint + non-biometric fallback). 7. Determine whether MarkManager/Geofence/receipt pipeline can be reused as-is. 8. Write gap analysis, recommendation (hardware approach + v1 scope), and named (not created) follow-up candidates in task notes. 9. Finalize.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
**Hardware options (acceptance criterion #1).** Rough market pricing from public listings, 2026-10-06:

| Option | Cost (USD-equivalent, per unit) | Integration complexity | Identification capability |
|---|---|---|---|
| Standalone USB fingerprint reader + existing PC/tablet | ~$25-30 (bare sensor) to ~$250 (attendance-grade USB reader) | Needs a vendor SDK (e.g. DigitalPersona, Futronic, SecuGen) wired into a purpose-built kiosk app; the app owns the 1:N match loop and calls our API. Moderate: one SDK integration, one kiosk app. | 1:N match against an enrolled fingerprint template store, same as a dedicated terminal — the hardware, not the mounting, is what matches. |
| Android tablet, built-in fingerprint sensor | Tablet only, ~$100-300 | Android's native `BiometricPrompt`/`BiometricManager` is single-owner and binary (match the one enrolled owner of the device, yes/no) — it has no 1:N "who is this" mode. It cannot identify which of N employees touched the sensor. **Ruled out** for true kiosk identification without a third-party fingerprint SDK replacing the OS biometric API entirely, at which point this collapses into the row above. | None, as shipped. |
| Dedicated biometric time-clock terminal (ZKTeco-class; also sold under Anviz, Suprema, Hikvision, and GeoVictoria's own "GeoVictoria Box") | ~$100-500 per unit (market listings; GeoVictoria/ZKTeco don't publish list prices, quote-gated) | Lowest integration work for the *identification* half — the terminal does fingerprint enrollment, 1:N matching, and usually card/PIN fallback natively. Needs a backend receiver for the device's push protocol (e.g. ZKTeco's ADMS: the device POSTs each punch event to a configured URL) or a polling job if push isn't available. One receiver endpoint, not a bespoke kiosk app. | 1:N, built-in, plus PIN/card modes already present as alternate reader modes on most of this device class (see Art. 7g note below). |
| Phone-based (today's mobile app, no shared device) | $0 incremental | None — already the plan | N/A — this is the "no kiosk" baseline the PRD already scopes; not a totem. |

**Recommendation on hardware, reasoned from the table:** a dedicated terminal is the lower-integration path specifically *because* it already solves 1:N identification and the non-biometric fallback in hardware; a tablet-based kiosk only looks cheaper until the 1:N identification gap is priced in — at that point it needs the same class of fingerprint SDK the dedicated terminal already ships with, for a worse user experience (a terminal's reader is purpose-built; a repurposed tablet app bolting on a USB reader is not).

---

**Identification/auth model (acceptance criterion #2).**

Today's model (`app/Http/Controllers/Api/TokenController.php`): `POST /api/sanctum/token` issues one Sanctum personal access token per `(user, device_name)` pair — `$user->tokens()->where('name', $validated['device_name'])->delete()` then reissues. The token *is* the identity: whoever holds it is the employee, 1:1, for the life of the session. Every mobile endpoint reads `$request->user()` directly as the puncher (`app/Http/Controllers/Api/MarkController.php:store()`).

A totem flips this because the device-session identity and the punching identity are two different things that change every few seconds (different employee touches the sensor each time), where today they are the same thing for the life of the token. Concretely, a totem needs:

1. **A device-level principal, not a `User`.** The totem itself needs to authenticate to the API as "this terminal, at this premise," not as any one employee. The cleanest shape given the existing stack: a new lightweight model (e.g. `KioskDevice`, `BelongsToOrganization` + premise-scoped) that owns its own Sanctum token, created by an admin from the web app (mirrors `TokenController`'s pattern, different principal type — Sanctum supports polymorphic tokenable models out of the box). Give that token a narrow custom ability (e.g. `kiosk:punch`) so a stolen terminal token can create punches for its premise and nothing else — it should not be able to do anything a `User` token can (read leaves, sign documents, etc).
2. **Per-punch employee identification, resolved server-side from the biometric/PIN input, not from the bearer token.** The totem's request carries *evidence of who touched the sensor* (a fingerprint template match result + employee reference from the terminal's own matcher, or a typed PIN/employee code), and the endpoint resolves that to a `User` explicitly — then calls `MarkManager::createMark($type, $identifiedUser, ...)` exactly as `MarkController::store()` does, just with `$identifiedUser` coming from the identification step instead of `$request->user()`.
   - **This needs no change to `MarkManager`.** `createMark(MarkType $type, ?User $user = null, ...)` already accepts an explicit `$user` override for exactly this reason (today it's used so `$user` defaults to `Auth::user()`, but the override path already exists and is exercised by nothing today — a totem would be its first real caller).
3. **Enrollment is a new concept.** Fingerprint *templates* (not raw images — Resolución 38 doesn't speak to this, but Chilean data-protection practice and every vendor researched store an encrypted/non-reversible template, never the image) need to be captured once per employee and associated with their `User` row, either in Kolvi's own DB (if the reader's SDK exposes raw template bytes, e.g. the USB-reader path) or left on the terminal's own onboard store (dedicated-terminal path, where Kolvi never sees the template at all and only receives a vendor-assigned employee ID back, which must then be mapped to a Kolvi `user_id` — an admin-maintained lookup table, one row per employee per terminal/terminal-group).
4. **Deactivation must be enforced live, not just at issuance** — same gap §7.1 A8 already flags for mobile tokens (`is_active` checked at token issue, not on every request); a totem makes this sharper because the device token *never* expires in the employee-token sense (it's the terminal's identity, issued once), so a terminal has no natural moment to recheck an individual employee's `is_active` except on every single punch. The per-punch employee lookup step above is exactly where that check must live.

---

**Resolución 38 Art. 7g — concrete design (acceptance criterion #3).**

Art. 7g's actual text (`docs/context/resolucion_38.txt:116-119`): systems must "always contemplate at least two different recognition alternatives" so a worker is never blocked by a problem with one identification mechanism; the employer must designate which is primary and which is secondary (in the employment contract or internal regulation); and **at least one of the two alternatives must not use biometric parameters or personal data** (their own example: passwords, patterns, or proximity cards).

Read literally, Art. 7g is a system-level requirement ("los sistemas... deberán contemplar"), not necessarily a single-device requirement — in principle, "fingerprint at the totem" + "password in the mobile/web app" could be the designated primary/secondary pair. In practice that reading fails the "nunca se vea impedido" (never blocked) intent the article opens with: if the totem is the *only* clocking channel at that entrance (the premise's whole reason for having one), an employee whose finger won't scan has no secondary method physically available to them at the point of failure — they'd have to leave the premise's designated marking point to find a phone, which defeats the purpose of a shared-device entrance control.

**Concrete recommendation: build the fallback into the totem screen itself**, not into a separate channel:
- **Primary: fingerprint**, via whichever hardware option is chosen above.
- **Secondary, on the same screen: a numeric employee PIN** (4-6 digits, admin-settable per employee, stored hashed like a password — never a plain card number, which Art. 7g explicitly excludes alongside biometrics as "personal data" only when it's a *proximity* card carrying an identifier; a typed PIN with no stored personal identifier is the safest reading). A card/badge reader is a reasonable alternative to a PIN, but adds a second hardware SKU and enrollment step the PIN avoids — **recommend PIN first, card as a later enhancement if badges already exist for building access.**
- The totem's UI always shows both paths (finger icon + "o ingresa tu PIN" link), never hides the fallback behind a failure state — Art. 7g's "primaria y secundaria" framing is about *regular use vs. backup*, and the employer's internal regulation is where that designation gets written down; the totem itself should offer both every time, consistent with "a lo menos dos alternativas... siempre."
- This is the same shape dedicated biometric terminals already ship (see hardware table: fingerprint + PIN + card as built-in alternate modes) — reinforces the dedicated-terminal recommendation, since building an equivalent dual-mode UI from scratch on a bare tablet is strictly more work than configuring a terminal that already has it.

---

**MarkManager / Geofence / signed-receipt pipeline reuse (acceptance criterion #4).**

Checked by reading the current implementation (`app/Managers/MarkManager.php`, `app/Observers/MarkObserver.php`, `app/Support/Geofence.php`, `app/Support/Folio.php`, `app/Http/Controllers/Api/MarkController.php`) — all built after the mobile PRD's baseline (`f0a4498`) and already more complete than that PRD describes (folio, checksum, offline queue support, geofence are all live, not gaps).

- **`MarkManager::createMark()` needs zero changes.** It already takes an explicit `?User $user` parameter instead of always reading `Auth::user()` — a totem calls it exactly like the mobile controller does, substituting the per-punch identified employee for the request's authenticated user.
- **`MarkObserver` needs zero changes.** The legal snapshot (RUT, names, premise, checksum, folio) is stamped from `$mark->user` and `$mark->premise`, not from "who is logged in" — it's already channel-agnostic.
- **Geofence is close to a formality for a totem, and that's fine as designed, not a reason to skip it.** `Geofence::fromPremise()`/`verdictFor()` key off the *premise's* fixed lat/lng + radius (`app/Support/Geofence.php`), never the device's own GPS — a totem has no GPS to report in the first place, so every totem punch would resolve `GeoStatus::Inside` (or `Unknown` if the premise has no radius configured) by construction, every time, with no server-side work needed to make that happen. **Recommend computing and storing it anyway** rather than special-casing totem punches to skip it: `AnomalyFlagReason::OutsideGeofence` and the overtime-anomaly pipeline (`app/Services/WorkdayCalculator.php`) already consume `geo_status` uniformly across all channels, and a totem punch that somehow *does* come back `Outside` (a premise whose configured lat/lng doesn't match where the totem physically sits — a real misconfiguration worth surfacing) would silently vanish from that review path if totem punches were special-cased out of it.
- **The one real gap is upstream of all three:** none of `MarkManager`, `MarkObserver`, `Geofence`, or `Folio` read the Sanctum token/guard at all — they only need a resolved `User`. All of the actual totem-specific work is in getting from "a fingerprint/PIN event at a shared terminal" to that resolved `User` (acceptance criterion #2), not in the pipeline downstream of it.

---

**Market check (acceptance criterion #5).** Full writeup: `docs/research/kiosk-biometric-competitor-research.md`.

Brief summary: **Talana** ships a tablet kiosk ("Talana Tótem") but sidesteps the biometric-fallback problem by making the kiosk's *only* method non-biometric (username + password + photo) — no fingerprint reader on the shared device at all. **Buk** positions itself purely as software that links to externally-sourced biometric hardware; nothing Buk-branded, no fallback guidance found. **GeoVictoria** is the only one of the three selling its own dedicated hardware — "GeoVictoria Box" (branded terminal, face+fingerprint) and "GeoVictoria USB" (USB reader + any PC/tablet) — matching both hardware paths this spike considered, but with no public mention of a non-biometric fallback on either device. None of the three publicly documents an Art. 7g-style dual-alternative design on the shared device itself; satisfying it appears to be left to generic terminal hardware (which, per the market-wide hardware check above, commonly ships fingerprint + PIN + card together) rather than something any of these three Chilean vendors specifically engineered or advertised.

---

**Recommendation (acceptance criterion #6): GO, with dedicated hardware over a tablet app, scoped small.**

**Hardware approach: a dedicated biometric time-clock terminal** (ZKTeco-class or equivalent — GeoVictoria's own "Box" product is evidence this class of device is already normalized among direct competitors), not a kiosk app on a commodity tablet. Reasoning, pulling the threads above together:
- A tablet path still needs a third-party fingerprint SDK for real 1:N identification (the OS biometric API can't do it) — at that point it has paid the same integration cost as a dedicated terminal while building a worse, bespoke UI for the Art. 7g PIN/card fallback that terminals already ship with.
- The backend work is the same shape either way (a receiver endpoint resolving an identified employee to `MarkManager::createMark()`), so the hardware choice doesn't change the riskiest part of the build.

**Rough v1 scope:**
1. One `KioskDevice` (or similarly-named) principal type with its own scoped Sanctum token, issued per terminal from the web app by an admin.
2. One enrollment flow: map each employee to the terminal vendor's own identification (their onboard 1:N match, returning a vendor-assigned ID) via an admin-maintained lookup table — avoids Kolvi ever handling raw biometric templates.
3. One receiver endpoint accepting the terminal's push event (vendor-specific protocol, e.g. ADMS-style), resolving the vendor ID to a `User`, and calling `MarkManager::createMark()` — reusing the existing one-punch-per-type-per-day guard, idempotency-key pattern, and geofence computation exactly as the mobile controller does.
4. PIN fallback entry on the terminal itself (most terminal classes support this as a built-in alternate mode, configured rather than built) satisfying Art. 7g without new Kolvi-side UI work.
5. Pilot at a single premise before any wider rollout, mirroring the mobile PRD's own phase-1 pilot approach.

**Candidate follow-up tickets (named only, not created):**
- Add a `KioskDevice`/terminal principal model with a Sanctum token scoped to a narrow `kiosk:punch`-style ability, premise-bound.
- Build the vendor-ID ↔ `User` enrollment/lookup admin screen.
- Build the terminal push-event receiver endpoint and wire it to `MarkManager::createMark()`.
- Decide and procure the specific terminal hardware/vendor (a purchasing decision, not an engineering one — out of this spike's scope).
- Write the employer-facing primary/secondary identification-method designation into onboarding docs/internal-regulation templates, per Art. 7g's requirement that this be stated in the contract or reglamento interno.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Research spike, no code changed. Internal findings: MarkManager/MarkObserver/Geofence/Folio pipeline (built after the mobile PRD's baseline) needs zero changes for a totem — MarkManager::createMark() already accepts an explicit $user override, and the legal snapshot/checksum/folio/geofence all key off the resolved user/premise, not the auth channel. The real gap is upstream: today's Sanctum token is 1:1 with an employee, so a totem needs a new device-level principal plus a per-punch identification step (fingerprint/PIN resolved server-side to a User) feeding that same createMark() call. Resolucion 38 Art. 7g read concretely: build a PIN fallback directly into the totem screen alongside the fingerprint reader, not into a separate channel, because the totem is the premise's only clocking point. Market check (Talana/Buk/GeoVictoria, written to docs/research/kiosk-biometric-competitor-research.md) found GeoVictoria is the only one selling dedicated hardware (Box terminal + USB reader), Talana's kiosk sidesteps biometrics entirely (password+photo), Buk ships no hardware of its own; none document an on-device non-biometric fallback. Recommendation: GO, dedicated biometric terminal over a tablet app (Android's native biometric API can't do 1:N matching, so a tablet path pays the same SDK-integration cost as a terminal while building a worse fallback UI), v1 scoped to a KioskDevice token type, an enrollment lookup, a push-event receiver, and a pilot premise. Candidate follow-ups named, not created, in task notes. Definition-of-Done items (pint/tests/types-check/Pest) are not applicable — no PHP or TS files were changed.
<!-- SECTION:FINAL_SUMMARY:END -->
