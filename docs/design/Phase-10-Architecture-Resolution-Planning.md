---
status: Final (Revision 2) — roadmap executed; all items resolved
last-updated: 2026-08-06
references: Phase 9 Revision 2, ADR-003, ADR-004, DG-SIS-001 (Resolved), Phase 6 Revision 2, Phase 7 Revision 2, Phase 8 Revision 2
---

# Phase 10 — Architecture Resolution Planning (Revision 2)

**Revision 2 (2026-08-06):** ADR-004 resolved every item (I1–I8) this roadmap identified — in essentially the order this document itself recommended (§Recommended resolution order, unchanged below). This is kept as the executed roadmap, not rewritten, since it correctly predicted the resolution order; a resolution column is added per item.

## Per-item analysis, with resolution

| Item | Resolution (ADR-004) |
|---|---|
| **I1 — `section_id` creation-time timing (DG-SIS-001)** | §1: nullable at creation, populated at FR-06 completion. |
| **I2 — Concrete SIS Service interface shape** | §3: `StudentService::createStudentStub`, concrete input/output. |
| **I3 — `CreateStudentRequest` shape/ownership** | §3: finalized as internal-only `StudentStubData`; resolved jointly with I2 exactly as this roadmap anticipated. |
| **I4 — Confirm Enrollment: new operation vs. extension** | §6: extension of the existing `Shortlisted`/`Waitlisted → Admitted` transition. |
| **I5 — Transaction boundary across the Admission↔SIS call** | §5: single local transaction, Admission-owned. |
| **I6 — Compensating/rollback behavior on SIS-side failure** | §5: none needed — atomic rollback covers it, resolved jointly with I5 as anticipated. |
| **I7 — Whether SIS's result includes the created `Student`'s identifier** | §3: yes — resolved as part of I2, as anticipated. |
| **I8 — `full_name`/`dob` nullability tension** | §2: both copied from the confirmed `Application`; never actually nullable, only missing a stated source. |

## Dependency graph and resolution order (as executed)

```
Tier 1 — I1, I8, I4 — resolved together in ADR-004 §1/§2/§6
        ▼
Tier 2 — I2 + I3 + I7 — resolved together in ADR-004 §3
        ▼
Tier 3 — I5 — resolved in ADR-004 §5
        ▼
Tier 4 — I6 — resolved jointly with I5 in ADR-004 §5 (turned out not to need separate sequencing — a single-transaction answer to I5 makes I6 a direct consequence, not a separate design step)
```

## Consistency verification

Checked against the full repository (ADR-001 through ADR-004, DG-SIS-001 Resolved, Phase 4.2–4.7 Revision 2, Phase 4 Closure Report Revision 2, Phase 5 Revision 2, Phase 6 Revision 2, Phase 7 Revision 2, Phase 8 Revision 2, Phase 9 Revision 2). No contradiction found — ADR-004 resolved every item in the order this roadmap laid out, with I6 collapsing into I5 rather than requiring independent resolution.
