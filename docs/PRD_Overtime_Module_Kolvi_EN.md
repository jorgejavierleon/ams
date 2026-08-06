# PRD — Reliable Overtime Module
**Project:** Kolvi (Chilean Attendance/Compliance Management System)
**Author:** Product Owner (collaborative draft)
**Status:** Draft v1
**Date:** August 2026

---

## 1. Executive summary

This module does **not** calculate the monetary value of overtime hours (that's the payroll system's job — Kolvi's own or a third-party one). Its responsibility is to **produce an overtime figure that is reliable, traceable, and defensible in a labor audit**, and to expose it through a structured export that the payroll system can consume without having to second-guess the data.

The problem it solves directly: an erroneous mark (e.g., a worker who ends up with 5 hours of overtime logged because of a bad punch, a desynced device, or a forgotten clock-out) **must never translate into a payment obligation** just because nobody corrected it in time.

---

## 2. Problem

The naive flow would be: `raw mark → subtract against shift → "overtime" → export to payroll`. This is fragile because:

- Marks can have human or technical errors (double punch, mark with no assigned shift, clock desync, forgotten clock-out).
- Chilean law requires that excess hours have employer knowledge/authorization behind them to be payable (Labor Code Art. 32 — the Labor Directorate, or DT, applies a "reality criterion": even without a written agreement, hours must be paid if the employer had knowledge — but knowledge and authorization are acts the system must *record*, not assume).
- A number with no approval trail is indefensible in an audit and creates either overpayment or disputes with workers.

## 3. Goals

1. No overtime reaches the payroll export without having passed through an explicit authorization state.
2. Every mark anomaly (exceeding legal caps, missing shift, incomplete mark) is automatically flagged and blocks progression to "approved" until human review.
3. Exported data can always be traced back to the original raw mark and to who approved what, and when.
4. Compliance with Resolución 38 EXENTA (Art. 43, 44, 45.2) regarding calculation precision, compensation options, and non-blocking alerts.

## Non-goals

- Calculating the peso value of overtime hours (payroll system's responsibility).
- Payroll/liquidation management.
- Sunday/holiday surcharge calculation as a standalone feature (referenced but not in scope here).

---

## 4. Glossary (based on market-validated terminology)

| Term | Definition |
|---|---|
| **Raw mark** | Immutable clock-in/out record exactly as received from the device or app. Never edited or deleted — it's the legal record. |
| **Shift excess** | Positive difference between what was marked and the assigned shift, not yet managed (equivalent to Buk's term). |
| **OHR (Overtime Hours Requested)** | Hours the employee requested to work, before or after the fact (equivalent to Talana's request flow). |
| **OHA (Overtime Hours Authorized)** | Hours a supervisor/admin explicitly authorized (equivalent to GeoVictoria's HEA). |
| **OHC (Overtime Hours Calculated)** | Automatic output of the calculation engine from raw marks, with no human intervention. |
| **Final approved overtime** | `MIN(OHR, OHA, OHC)` — the value actually exported to payroll (rule taken directly from Talana's flow). |
| **Objection** | Explicit rejection of a shift excess by a supervisor (equivalent to "objetar" in Buk). |
| **Anomaly** | Automatic flag when a calculation exceeds configured parameters (legal cap, missing shift, incomplete mark). |
| **Overtime agreement (pacto)** | Document with a maximum 3-month validity, renewable, required by Labor Code Art. 32 to authorize recurring overtime. |

---

## 5. Applicable legal framework (operational summary)

- **Labor Code Art. 30-32**: cap of 2 extra hours/day, 12 extra hours/week, ordinary + extraordinary hours ≤ 12h/day. Minimum 50% surcharge on base salary (surcharge calculation is NOT this module's responsibility).
- **Current ordinary workweek**: 42 hours/week as of April 26, 2026, decreasing to 40h by 2028 (Law 21.561). The system must handle this as a date-versioned parameter, not a hardcoded constant.
- **Resolución 38 EXENTA**:
  - Art. 44 — calculation precision down to the hour/minute/second, with no rounding that favors either party.
  - Art. 43 — the system must offer two compensation modes (payment in payroll vs. additional rest days), and absent a written agreement stating otherwise, payment is assumed.
  - Art. 45.2 — on-screen alert when a loaded schedule exceeds legal caps; **the alert never blocks the entry**, it's advisory only.
  - Art. 45.3 — if rest-day compensation was agreed, an automatic email every 30 days showing accrued hours and their expiration date.

---

## 6. Roles

| Role | Relevant permissions |
|---|---|
| **Employee** | Can request overtime (if the company enables this mode). Sees their own request history and status. |
| **Supervisor / Team lead** | Approves or objects to their team's requests and shift excesses. Cannot modify legal-cap configuration. |
| **HR Admin** | Configures company policies (authorization mode, caps, default surcharges). Reviews anomalies. Generates the payroll export. |
| **System (calculation engine)** | Automatically generates OHC, applies legal-cap validation, generates anomaly flags. |

---

## 7. Functional requirements

### 7.1 Authorization mode (configurable per company)

The system must support two modes, mirroring what the market has already validated:

- **Mode A — Pre-authorization (Talana-style)**: the employee requests the overtime *before* working it (same day, future, or past if the company allows it); the supervisor approves or rejects; only then does the system calculate. This is the most airtight mode against marking errors.
- **Mode B — Post-hoc / management by exception (Buk-style)**: marks automatically generate "shift excess"; a supervisor reviews and approves or objects afterward, individually or in bulk.

A company can combine both (e.g., Mode A for planned overtime, Mode B as a safety net for unrequested excess).

**Golden rule**: regardless of mode, the exported final value is always `MIN(OHR, OHA, OHC)` when all three exist, or simply `OHC` capped by legal-cap validation when there was no prior request (pure Mode B) — but in that latter case, the record stays in "pending approval" status until a human confirms it.

### 7.2 Automatic calculation engine (OHC)

- Runs as an async job after shift close-out (or near-real-time if the shift allows).
- Calculates shift excess as `(last mark − shift end time)`, based exclusively on marks and assigned shift — never inferred when there's no shift.
- Applies hour:minute:second precision (Resolución 38, Art. 44), no rounding.
- **Never writes directly to an "approved" state.** The output of this calculation can reach "pending review" at most.

### 7.3 Legal-cap validation

Before any overtime can move to "approved" status, the system validates:

- ≤ 2 extra hours per day (versionable parameter if the law changes).
- ≤ 12 extra hours per week.
- Ordinary + extraordinary hours ≤ 12h/day and ≤ 52h/week.
- On excess: **the operation is not blocked** (as required by Resolución 38 Art. 45.2, and as confirmed by GeoVictoria — the system allows authorizing more than 2h/day for exceptional cases like critical-service continuity), but an `AnomalyFlag` is generated and a written justification from the admin is required to approve the excess.

### 7.4 Anomaly detection (independent of legal caps)

Automatically flags — and blocks progression to "approved" without review — when:

- The employee has no assigned shift that day.
- Only one mark exists for the day (clock-in without clock-out or vice versa).
- The contract is not active on the marked date.
- The mark was made outside the expected geofence/device (if the company uses geolocation).
- Total overtime for the period exceeds a configurable threshold (e.g., >10h/week) — a signal of possible shift misuse or marking error.

### 7.5 Approval / objection flow

- "Pending overtime" queue for supervisors, with individual or bulk approval (Buk-style).
- Every approval/objection records: acting user, timestamp, and optional reason (mandatory if it exceeds the legal cap).
- Resulting status: `approved`, `objected`, or `pending` (never auto-approved by timeout — an ungoverned record simply isn't exported, it's not assumed approved by default).

### 7.6 Overtime agreements (pactos)

- CRUD for agreements with a 3-month max validity (Art. 32), renewable.
- Alert when an agreement is about to expire.
- An overtime record can optionally link to a valid agreement; if none exists and the company requires one, the record stays "pending" with an explicit reason.

### 7.7 Payroll export

- The export **only reads from records in `approved` status**. This is a query/view-level constraint, not a UI convention — the technical design must make it impossible for any status other than `approved` to appear in the export dataset.
- Format: structured CSV/Excel + optional API/webhook for direct integrations (Buk, Talana, Rankmi, Previred as destinations, per the integrations roadmap already identified in the gap analysis).
- Each exported line includes: RUT, date, approved hours, day type (weekday/Sunday/holiday), agreement reference (if any), approver, and approval timestamp — for audit traceability.
- Log of what was exported and when (to prevent double-exporting the same period).

### 7.8 Traceability and audit

- Always preserved: raw mark → OHC → OHR (if applicable) → OHA/approval → final exported value.
- Any manual mark correction is logged with a "manual correction" flag, user, and timestamp (same pattern as Talana's manual-correction column in its reports).
- Exportable audit report aligned with Resolución 38's four mandatory DT reports.

---

## 8. Data model (high level)

```
AttendanceMark (immutable)
 ├─ id, employee_id, timestamp, type (in/out), source (app/clock/manual), geolocation?

WorkdayCalculation
 ├─ id, employee_id, date, shift_id, ordinary_hours, ohc_hours, anomaly_flags[]
 ├─ calculated_at, legal_cap_version_id (references the legal workweek in effect on that date)

OvertimeRequest (OHR) — only if Mode A is active
 ├─ id, employee_id, requested_date, requested_hours, requested_at, status

OvertimeAuthorization (OHA + final status)
 ├─ id, workday_calculation_id, request_id?, pact_id?
 ├─ oha_hours, ohc_hours, ohr_hours, final_hours (= MIN of the available values)
 ├─ status (pending | approved | objected)
 ├─ approved_by, approved_at, justification (mandatory if exceeding cap)

OvertimePact
 ├─ id, employee_id, start_date, end_date (max 3 months), status

OvertimeExportBatch
 ├─ id, period_start, period_end, generated_at, generated_by, record_count
 └─ OvertimeExportLine[] (immutable snapshot of what was exported)
```

---

## 9. Non-functional requirements

- **Precision**: calculations to the second, no intermediate rounding (rounding only, if applicable, at the final report presentation layer).
- **Non-blocking**: no legal-cap validation prevents saving/loading a shift or mark — it only generates alerts (Resolución 38, Art. 45.2).
- **Raw mark immutability**: no system operation should allow editing or deleting an original `AttendanceMark`; corrections are modeled as new linked records, not destructive updates.
- **Auditability**: every state change must be logged in an append-only log.
- **Multi-tenant**: legal-cap parameters, authorization mode, and surcharges are configurable per company (tenant), not global.

---

## 10. Per-company configuration

- Mode: Pre-authorization / Post-hoc / Combined.
- Written agreement requirement: Yes/No.
- Weekly volume anomaly threshold (suggested default: 10h).
- Lookback window for retroactive requests (Mode A).
- Default compensation type: payroll payment vs. rest days (Art. 32, paragraph 4).

---

## 11. Success metrics

- % of exported overtime hours with an explicit approval record: **100%** (non-negotiable, it's the module's core objective).
- Average time between mark and anomaly resolution.
- % of anomaly-flagged records that end up objected (quality indicator for marking practices / training needs).
- Zero incidents of duplicate export for the same period.

---

## 12. Risks and open questions

- What happens when a company never manages a shift excess (neither approves nor objects)? Legally, silence with knowledge could still trigger a payment obligation under the DT's "reality criterion" — suggested mitigation: an alert report to HR when records stay "pending" past X days, so that lack of action doesn't become a legal risk for the client.
- Should the volume anomaly threshold (10h/week suggested) be configurable by industry? Critical shifts (IT, healthcare) may justify higher spikes, as GeoVictoria notes in its FAQ about critical-service continuity.

---

## 13. Suggested Jira epics

1. **EPIC — Raw marks and calculation engine (OHC)**: immutable ingestion, automatic calculation, date-versioned legal workweek.
2. **EPIC — Overtime request (OHR)**: employee → supervisor flow, Mode A.
3. **EPIC — Post-hoc management queue (OHA)**: individual and bulk approval/objection, Mode B.
4. **EPIC — Cap and anomaly validation engine**: business rules, non-blocking flags.
5. **EPIC — Overtime agreements**: CRUD, validity, expiration alerts.
6. **EPIC — Payroll export**: batch generation, formats, double-export prevention, integrations.
7. **EPIC — Audit and traceability**: logs, DT audit reporting.
8. **EPIC — Per-tenant configuration**: mode, thresholds, versioned caps.

---

*Living document — recommend validating the risk section with a labor law advisor before defining default behavior around rest-day compensation.*
