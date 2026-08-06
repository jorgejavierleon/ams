---
id: m-2
title: "Módulo de Horas Extras (HHEE)"
---

## Description

Produce an overtime figure that is reliable, traceable and defensible in a labor audit, and expose it through a structured export the payroll system can consume without second-guessing the data.

The module does **not** calculate the peso value of overtime — that is payroll's job. Its responsibility is that no hour reaches the payroll export without having passed an explicit human authorisation, that every anomaly is flagged before it can be approved, and that any exported figure traces back to the raw mark and to who approved it, when.

Core problem: an erroneous mark (bad punch, desynced device, forgotten clock-out) must never become a payment obligation just because nobody corrected it in time.

Source: docs/PRD_Overtime_Module_Kolvi_EN.md. Market shape validated against Talana (request/approve flow, MIN rule) and GeoVictoria (HEA authorised vs HEC completed, non-blocking over-cap authorisation for critical-service continuity).
