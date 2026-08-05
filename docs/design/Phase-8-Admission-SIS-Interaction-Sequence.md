---
status: Approved (Revision 2) — fully finalized
last-updated: 2026-08-06
references: ADR-003, ADR-004, DG-SIS-001 (Resolved), Phase 6 Revision 2, Phase 7 Revision 2
---

# Phase 8 — Admission ↔ SIS Interaction Sequence Design (Revision 2)

Architecture-level sequence documentation only. No DTO, REST, or database design. Synthesizes Phase 6 Revision 2 (Admission side) and Phase 7 Revision 2 (SIS interface contract) into a single interaction sequence.

**Revision 2 (2026-08-06):** every item Revision 1 left Open is resolved by ADR-004.

## 1. Interaction trigger

Admin Staff triggers "Confirm Enrollment" on an application (FR-02 §9) once it is `Shortlisted` or `Waitlisted` (ADR-004 §6, resolving FR-02 §7's "Approved" mismatch). **Admission.**

## 2. Sequence of responsibilities

1. Admin Staff selects an application and triggers Confirm Enrollment — **Admission**
2. Re-validate seat capacity (BR-ADM-001) and RTE quota ceiling (BR-ADM-003), incrementing `SeatAllocation`'s counters under a pessimistic row lock — **Admission**
3. Re-validate applicant identity/duplicate status (FR-05a, BR-ADM-006) — **Admission**
4. Generate the Admission Number — **Admission**
5. Call `StudentService::createStudentStub` to create the `Student` stub — **Shared / Cross-module** (Phase 7 Revision 2), within the transaction opened in step 2
6. SIS persists the `Student` stub, enforcing BR-SIS-002 — **SIS**
7. SIS returns the created `student_id` to Admission — **Shared / Cross-module** (Phase 7 Revision 2 §8)
8. `Application.status` → `Admitted` — **Admission**
9. Transaction commits — **Admission** (owns the transaction boundary, ADR-004 §5)

FR-02 §10's Main Flow lists steps 1–4 before step 5, in this order.

## 3. Admission responsibilities before calling SIS

Steps 1–4: trigger handling, capacity/RTE re-validation, identity/duplicate re-validation, Admission Number generation. FR-02 §10 lists these four steps before step 5 in this order, and §12 describes a capacity or identity failure as blocking confirmation (waitlist redirect; held for manual review) rather than proceeding.

## 4. SIS responsibilities after receiving the request

Persist the `Student` stub with `full_name`/`dob`/`category`/`aadhaar_number` copied from the `Application` (ADR-004 §2), `section_id`/`medical_info` left `NULL`; enforce BR-SIS-002 during persistence; throw on failure, which propagates to Admission's transaction. SIS does not perform Admission's capacity/identity checks or Admission Number generation, and does not open its own transaction for this call.

## 5. Control returned back to Admission

Control returns to Admission's orchestration after `createStudentStub` returns the created `student_id`. Admission proceeds to step 8 only on success.

## 6. Success path

Capacity/RTE checks pass → identity/duplicate check passes → Admission Number generated → SIS persists the stub successfully, returning `student_id` → `Application.status` → `Admitted` → transaction commits → Admission Number, updated status, and `student_id` are Admission's own output.

## 7. Failure path

- **Pre-call Admission-side failure** — seat capacity breached → waitlist redirect (BR-ADM-001); duplicate identity → manual review (BR-ADM-006). Occurs before SIS is ever called, no transaction to roll back yet. **Admission.**
- **Post-call SIS-side failure** — persistence itself fails; exception propagates. The shared transaction (step 2–9) rolls back atomically — the seat-count increment and any partial state revert. **No compensating action is designed or needed** (ADR-004 §5).

## 8. Responsibilities — all resolved

Every item Revision 1 tracked in this section is resolved by ADR-004: operation identity (§1), required-input field set (Phase 4.4/7 Revision 2), `section_id` handling (DG-SIS-001, Resolved), result content (`student_id` included), compensating/rollback behavior (none needed), and transaction boundary (single local transaction, Admission-owned).

## 9. Dependency direction verification

Admission depends on SIS's Service method for step 5; SIS never calls back to Admission within this sequence. Matches ADR-003 and the Company Development Standard's §1.1 no-cycle rule. **Verified consistent.**

## 10. Sequence consistency against ADR-003/ADR-004

ADR-003 assigns trigger/orchestration to Admission and persistence to SIS; ADR-004 fills in the concrete mechanics. This sequence reflects exactly that split. **Verified consistent — no contradiction found.**

## Review against the complete approved documentation repository

Checked against the full baseline (ADR-001 through ADR-004, DG-SIS-001 Resolved, Phase 4.2–4.7 Revision 2, Phase 4 Closure Report Revision 2, Phase 5 Revision 2, Phase 6 Revision 2, Phase 7 Revision 2). No contradiction found.
