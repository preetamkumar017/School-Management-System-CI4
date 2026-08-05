---
status: Final (Revision 2)
last-updated: 2026-08-06
---

# Phase 5 — SIS Implementation Planning (Revision 2)

**Revision 2 (2026-08-06):** ADR-004 resolved every gate Revision 1 tracked (DG-SIS-001, the SIS Service method shape, the transaction boundary). This revision replaces the gated sequence with a flat one — nothing in this module blocks on an external decision anymore. Only the Admission-side implementation (its own Phase 1–7) remains a genuine cross-module scheduling dependency, not an architectural one.

## 1. Implementation sequence

1. **Guardian vertical** — fully approved, no dependency on anything.
2. **`Student` entity, Model, mapper, service, controller** — fully approved (ADR-004), no gate remaining.
3. **`StudentGuardianLink` entity, Model, mapper, service, controller** — depends only on `Student`'s table existing (step 2), same module.
4. **Cross-module wiring**: Admission's `AdmissionService`'s Confirm Enrollment method calls `StudentService::createStudentStub` within its own transaction (ADR-004 §5) — this requires Admission's own implementation (`docs/design/admission/Phase-1` through `Phase-7`) to exist, a scheduling dependency on another module's build order, not an open design question.
5. **End-to-end integration testing**, including BR-SIS-002/003/005/006, once step 4's wiring is in place.

## 2. Component dependency graph

```
Guardian (entity/Model/mapper/service/controller)      ── independent, no dependencies

Student (entity/Model/mapper/service/controller,
including createStudentStub and changeStatus)          ── independent, no dependencies (ADR-004)
        │
        ▼
StudentGuardianLink (entity/Model/mapper/service/controller) ── depends on Student's table

Student.createStudentStub ◄── called by ── Admission's AdmissionService
                                            (Confirm Enrollment, within Admission's own
                                            transaction — see docs/design/admission/Phase-4)
```

## 3. Components ready for immediate implementation

| Component | Classification |
|---|---|
| `Guardian` — entity through controller | **Ready** |
| `Student` — entity through controller, including `createStudentStub` and `changeStatus` | **Ready** (ADR-004) |
| `StudentGuardianLink` — entity through controller | **Ready**, sequenced after `Student`'s table exists (same module, not a cross-module gate) |

No component in this module is blocked. The only genuine dependency is scheduling: `Student.createStudentStub` isn't exercised end-to-end until Admission's Confirm Enrollment orchestration also exists — that's a build-order question across two modules' implementation schedules, not an open design item in either.

## 4. Recommended implementation milestones

- **M1** — Guardian vertical complete.
- **M2** — Student vertical complete (entity through controller, all operations).
- **M3** — StudentGuardianLink vertical complete (requires M2).
- **M4** — Admission's Confirm Enrollment calls `Student.createStudentStub` successfully in integration test (requires M2 and Admission's own implementation).
- **M5** — Full SIS module integration testing, including BR-SIS-002/003/005/006 end to end (requires M3 and M4).

## 5. Risks during implementation

- **Cross-team/cross-module scheduling risk** — M4 depends on Admission's implementation timeline, outside this plan's control. Unlike Revision 1's risk of the same shape, this is now a scheduling dependency only — the design on both sides is finalized (ADR-004), so there's no risk of the two sides disagreeing on contract once both are built.
- **Untestable business rule risk** — BR-SIS-006 (and the full `createStudentStub` → `changeStatus` → BR-SIS-003 path) can't be integration-tested until M4, since it requires a real `Application` to confirm against.

## 6. Exit criteria for each milestone

- **M1:** `Guardian` CRUD passes tests.
- **M2:** `Student` CRUD, `createStudentStub`, `transferSection`, and `changeStatus` (both BR-SIS-003 and BR-SIS-006 portions) pass tests, called directly (not yet via Admission).
- **M3:** `StudentGuardianLink` CRUD/link/unlink pass tests against a real `Student` table.
- **M4:** Admission's Confirm Enrollment sequence calls `createStudentStub` successfully in an integration test, within a single shared transaction (ADR-004 §5), and a forced `createStudentStub` failure correctly rolls back Admission's seat-count increment and status change.
- **M5:** Full SIS test suite passes, including BR-SIS-002/003/005/006 end to end.

## 7. Overall implementation readiness assessment

**Ready.** Every component in this module has an approved design at every layer (ADR-004 closed the last gates). The only remaining dependency is cross-module build scheduling with Admission, not an unresolved design question.
