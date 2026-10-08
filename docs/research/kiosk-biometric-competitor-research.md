# Kiosk / biometric shared-device attendance — brief competitor check for KOL-147

**Research question:** What do Talana, Buk and GeoVictoria offer for a shared kiosk/totem device at a premise entrance with a fingerprint reader, and do any of them document a non-biometric fallback alongside biometric identification? This is a **light market check**, not the deep per-competitor research KOL-146 already did for rotating shifts — a handful of public-source searches, not an exhaustive audit.

**Date of research:** 2026-10-06. **Method:** public search results, vendor marketing/blog pages and one help-center article, fetched live. No login/trial account was used. Claims are cited with their source; anything not found in a public source is marked unverified rather than guessed.

---

## Talana

Talana ships a dedicated tablet kiosk mode, **"Talana Tótem"**, for Android tablets. Per public search results summarizing Talana's own help center: the worker enters **username + password** on the shared tablet, selects entry/exit, and the app captures a photo of them at that moment.

Talana's own app also supports fingerprint/facial login, but that is described as a *personal-device* convenience for unlocking the Talana Next app faster (replacing typing a password on the employee's **own** phone) — not as the identification mechanism on a shared kiosk. Could not confirm from a public source whether Talana Tótem has ever supported an actual fingerprint-reader peripheral rather than password + photo; the password-first design suggests it has not needed one.

**Non-biometric fallback:** Talana Tótem's primary identification (username + password) *is itself* the non-biometric method — there is no fingerprint path to fall back from on this specific product. This sidesteps Art. 7g's two-alternatives rule by never introducing a biometric primary on the shared device in the first place, rather than by pairing biometric + non-biometric on the same unit.

*Source: public search-engine summary of Talana's help-center article "Sistemas de marcaje" (`ayuda.talana.com`); the live page itself returned Cloudflare's bot-challenge (consistent with KOL-146's research note that this help center blocks automated fetches) so this is a secondary citation, not a direct quote — flagged as such.*

## Buk

Buk's own blog frames its role as **software, not hardware**: fetched directly, `buk.cl`'s "¿Qué es la asistencia con huella digital?" article describes fingerprint attendance in general terms and positions "Buk Control de Asistencia" as a platform that **links to** an external biometric reader/clock, rather than a Buk-branded terminal or tablet app. No Buk-specific kiosk/tablet product was found; no PIN/card fallback is mentioned anywhere in that article — Buk's content simply doesn't address the shared-device identification problem from the hardware side at all.

**Non-biometric fallback:** not addressed in anything found publicly. If Buk's customers run a biometric clock, the two-alternative/non-biometric-fallback requirement would be the clock hardware's problem to solve (see GeoVictoria and the generic hardware note below), not something Buk's own software layer appears to speak to.

*Source: [¿Qué es la asistencia con huella digital? — Buk](https://www.buk.cl/blog/asistencia-con-huella-digital-adios-al-reloj-control), fetched live, 200 OK.*

## GeoVictoria

The most hardware-forward of the three, and the only one with its own branded terminal line, per GeoVictoria's own marketing pages:

- **GeoVictoria Box** — a dedicated time clock (3G/WiFi/LAN) with facial *and* fingerprint biometrics, advertised as storing up to 100,000 faces and able to distinguish a live face from a photograph.
- **GeoVictoria USB** — a USB fingerprint reader that plugs into any Windows PC or tablet with internet, i.e. the "commodity device + USB reader" path named in this spike's description, sold by GeoVictoria itself rather than left to the customer to source.
- **GeoVictoria App** — the phone-based alternative (geolocation + facial biometric), analogous to Kolvi's own mobile-app direction.

This maps directly onto the two hardware approaches this spike is choosing between (dedicated terminal vs. commodity tablet + USB reader) — GeoVictoria sells both, as separate SKUs, rather than picking one.

**Non-biometric fallback:** not found in any public page fetched, including a dedicated blog post on "¿Qué es un Huellero Digital?" which describes only the fingerprint-match step itself ("El empleado coloca su dedo en el lector biométrico... se registra automáticamente la hora"). No PIN/card fallback, no mention of Art. 7g, in anything public.

*Sources: [Reloj Control](https://info.geovictoria.com/es-cl/asistencia-reloj), [¿Qué es un Huellero Digital...?](https://www.geovictoria.com/es-cl/blog/operaciones/huellero-digital-que-es-y-como-funciona-en-el-control-de-asistencia/), both fetched live, 200 OK.*

## Cross-vendor takeaway on Art. 7g

None of the three vendors' public marketing/help content advertises an explicit non-biometric fallback *on the biometric device itself*. The one that does address identification redundancy at all — Talana Tótem — does so by making the kiosk's **only** method non-biometric (password), which is a different design than "fingerprint reader with a PIN/card escape hatch," closer to this spike's "non-biometric fallback alongside the reader" framing is a gap all three leave to either (a) the generic terminal hardware (dedicated biometric terminals in the wider market — not these three vendors specifically — commonly ship fingerprint *and* card *and* PIN as built-in alternate modes; see the hardware-options note in the task) or (b) an unexplored second product/channel (e.g. the employer separately designating the mobile/web app as the legal "secondary" method). This is consistent with Art. 7g itself being a system-level requirement ("los sistemas deberán contemplar... dos alternativas") rather than a mandate that one single physical unit offer both — but a totem that is the *only* clocking channel at that entrance still needs its own fallback, or an employee stuck at a dead sensor has no secondary method physically available to them, which is exactly the failure Art. 7g exists to prevent.

No cost figures were published by any of the three vendors for their hardware (GeoVictoria Box/USB, Talana Tótem tablet requirements) — pricing is quote-gated in all three cases, consistent with typical Chilean HRTech sales motion.
