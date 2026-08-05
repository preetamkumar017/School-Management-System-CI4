---
status: Final (Revision 2)
last-updated: 2026-08-06
references: ADR-003, ADR-004, DG-SIS-001 (Resolved), Phase 4 SIS design set, docs/design/admission/Phase-1 through Phase-5
---

# Phase 6 — Admission Service Design (FR-02 Confirm Enrollment), Revision 2

Scope: Admission's own Service-layer responsibility for FR-02 only, as reassigned by ADR-003. Concrete cross-module contract details (method names, locking mechanism) live in `docs/design/sis/Phase-4.6` and `docs/design/admission/Phase-4`, not here — this document stays at orchestration-sequence level.

**Revision 2 changes from Revision 1:** every item Revision 1 left Open is resolved by ADR-004 — see §5, §6, §8, §9, §10 below.

## Preliminary finding — a documentation gap in FR-02 itself, resolved (ADR-004 §6)

FR-02 §7 "Preconditions" states: *"Application status is 'Approved.'"* No such status exists among the application's documented status values (Submitted, Verified, Shortlisted, Waitlisted, Admitted, Rejected). Per ADR-004 §6, "Approved" is read as referring to either `Shortlisted` or `Waitlisted` — both represent an application past verification and on a positive track toward admission, and FR-02 doesn't distinguish between them for this purpose. Confirm Enrollment is **not** a new operation — it is the existing `Shortlisted`/`Waitlisted` → `Admitted` transition (`docs/design/admission/Phase-4`'s `ApplicationService`), extended with Admission Number generation, seat/RTE re-validation, and the SIS stub-creation call.

## 1. `AdmissionService` responsibilities for Confirm Enrollment

| Responsibility | Belongs to |
|---|---|
| Re-validate seat capacity (BR-ADM-001) and RTE quota ceiling (BR-ADM-003) at confirmation time (FR-02 §16) | **Admission** |
| Re-validate applicant identity/duplicate check (FR-05a, BR-ADM-006) | **Admission** |
| Generate the Admission Number (FR-02 §10 step 4) | **Admission** |
| Transition `Application.status` to `Admitted` (FR-02 §13) | **Admission** |
| Trigger creation of the `Student` stub (FR-02 §10 step 5) | **Shared / Cross-module** — Admission triggers, SIS persists, per ADR-003 |
| Persist the `Student` stub itself | **SIS** (ADR-003) |

## 2. Orchestration sequence (FR-02 §10 Main Flow)

1. Admin Staff selects an application and triggers Confirm Enrollment — Admission.
2. System re-validates seat capacity/RTE ceiling — Admission, corroborated by existing Admission validation and existing Admission concurrency handling.
3. System re-validates identity/duplicate status — Admission, corroborated by existing Admission validation.
4. System generates the Admission Number — Admission, per FR-02's own step ordering.
5. System creates the linked `Student` stub — Shared/Cross-module, via SIS's published Service interface (not designed here).
6. `Application.status` → `Admitted` — Admission.

## 3. Validations belonging to Admission before calling SIS

- BR-ADM-001, BR-ADM-003, FR-05a/BR-ADM-006 — Admission.
- Admission Number uniqueness for the academic year is an Admission responsibility (FR-02 §16). FR-02 specifies the responsibility, not the implementation mechanism — out of scope for this Service Design.

## 4. Responsibilities remaining inside SIS

- Persisting the `Student` stub (ADR-003).
- BR-SIS-002 uniqueness checks (Phase 4.3).
- Everything from FR-06 onward.

## 5. Transaction boundary — resolved (ADR-004 §5)

A single local database transaction, opened and committed by `AdmissionService`'s Confirm Enrollment method, spans the entire sequence: seat capacity/RTE re-validation, the `SeatAllocation` counter increment, the SIS stub-creation call, and the `Application.status → Admitted` transition. No distributed transaction and no compensating-action pattern — a SIS-side failure rolls back the whole transaction atomically, so there is nothing left to compensate for.

## 6. Cross-module dependency per ADR-003 — shape finalized (ADR-004 §3)

Admission depends on SIS's `StudentService::createStudentStub` method (concrete shape in `docs/design/sis/Phase-4.6`). This is an internal call, not a public API request — Admission's Service layer invokes it directly as part of the transaction in §5.

## 7. Required repository interactions inside Admission only

- `ApplicationRepository` — find/update `Application`. Already exists.
- `SeatAllocationRepository` — used to perform seat-capacity/RTE re-validation. Existing repository support already exists; no new Admission repository required.

## 8. Required outputs — resolved (ADR-004 §3)

Admission Number, updated `Application.status = Admitted` (FR-02 §13/§14/§15), and the created `Student`'s `student_id`, returned by `createStudentStub` within the same call (ADR-004 §3) — useful to Admission's own response and no reason to withhold it.

## 9. Failure scenarios

- Seat capacity breached → blocked, waitlist redirect (BR-ADM-001) — Admission, matching already-supported Admission behavior.
- Duplicate identity → held pending manual review (BR-ADM-006) — Admission.
- Concurrent confirmation for the same seat — resolved via pessimistic row-level locking on `SeatAllocation`'s counters (`docs/design/admission/Phase-4`'s decision, ADR-004 §5's transaction wraps it).
- Confirmation attempted with missing verified documents (BR-ADM-005) — already acknowledged as unimplemented/out-of-scope in existing Admission documentation. Still a pre-existing gap, unaffected by ADR-004 — out of scope for this resolution.
- SIS rejects/fails the stub-creation call after Admission-side state changes — resolved (ADR-004 §5): the shared transaction rolls back entirely; there is no post-hoc compensation to design.

## 10. Open items

None remaining. All six items Revision 1 tracked are resolved by ADR-004, except the BR-ADM-005 missing-documents gap, which is a pre-existing, unrelated Admission documentation gap out of scope for this resolution.

## Status

Finalized. Checked against ADR-001 through ADR-004, DG-SIS-001 (Resolved), Phase 4 Closure Report Revision 2, Phase 5 Implementation Plan Revision 2, and `docs/design/admission/Phase-1` through `Phase-5`: no contradictions.
