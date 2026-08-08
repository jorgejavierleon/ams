---
id: decision-1
title: No configuration may make a worked hour unpayable
date: '2026-08-08 15:26'
status: accepted
---
## Context

Four separate findings in the overtime module (m-2) turned out to have the same shape. Each was written to protect the employer from paying too much, and each achieved that by making hours that were actually worked, with the employer's knowledge, structurally impossible to pay:

1. **Pre-shift excess** (KOL-38). With the tenant setting off, early-arrival work is recorded, visible — and unpayable, because the golden rule caps the payable figure at OHC and the excess never reaches it. KOL-55 exists as the escape hatch.
2. **Default compensation type** (KOL-56, removed). A tenant-wide default of rest days would have compensated a worker with no pacto in time off instead of money, against Res. 38 art. 43, which fixes payment as the fallback.
3. **Pacto requirement** (KOL-57, removed). A setting making an overtime record unapprovable without a valid pacto, when the DT reality criterion — echoed by the PRD itself at line 22 — holds that hours worked with the employer's knowledge are payable whether or not a written agreement exists.
4. **The OHR term in the golden rule** (KOL-46, amended). `MIN(OHR, OHA, OHC)` pays the requested amount when a supervisor authorises *more* than was requested and the worker works it — underpaying hours that were both authorised and worked.

In Chile the legal and financial risk sits on the other side. An employer who underpays authorised, worked hours is the one exposed, and a system that produces that outcome by configuration makes Kolvi a party to it.

## Decision

**No tenant configuration, default, or arithmetic rule may render unpayable an hour that was actually worked with the employer's knowledge. Where the business needs a control, the control is a flag plus a mandatory written justification — never a block, and never a silent reduction of the payable figure.**

This is a standing review criterion for every task in the overtime module, applied when a task is picked up and again before it is finalised. A task that introduces a setting, a default, or a cap must state which of the two it is: a *signal* (flag, alert, required justification) or a *bar* (blocks payment). Bars are not permitted unless the law itself imposes them.

## Consequences

- **The shape to copy already exists in the source.** Res. 38 art. 45.2, on excessive shifts, states that the alert *"no impedirá la carga de la jornada, sino que sólo constituirá un aviso para el empleador"*. KOL-41 implements exactly this for legal caps: validate, flag, demand a written reason, never block. New controls follow that pattern.
- Legal ceilings remain the one legitimate hard limit, and they are global and date-versioned in `legal_hour_limits` (KOL-36), never per tenant.
- A recurring unpayable-by-design gap is a product defect, not a policy choice. Where one is unavoidable in the short term, it needs a named escape hatch (as KOL-55 is for KOL-38) rather than being left implicit.
- Applying this criterion is what removed two tenant settings that had already shipped in KOL-37. Expect it to keep removing configuration rather than adding it: the safe default is that the law decides, not the tenant.
- The Código del Trabajo text is **not** in the repo (`docs/context/` holds only `resolucion_38.txt` and `firma_electronica.txt`). Art. 32 claims in these tasks rest on the PRD summary and on memory, and must be confirmed against the statute with the labor advisor before KOL-42 and KOL-47 are finalised.
