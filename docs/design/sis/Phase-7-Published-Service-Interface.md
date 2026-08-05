---
status: Approved (Revision 2) — fully finalized, concrete contract designed
last-updated: 2026-08-06
references: ADR-003, ADR-004, DG-SIS-001 (Resolved), Phase 4.2 Revision 2, Phase 4.4 Revision 2, Phase 4.6 Revision 2, Phase 6 Revision 2
---

# Phase 7 — Published SIS Service Interface Design (Revision 2)

**Revision 2 (2026-08-06):** ADR-004 designs the concrete contract this document's Revision 1 deliberately left unshaped. The architectural relationships below (§1–6, §11) are unchanged from Revision 1; §7–10 and §12 are updated to reflect the finalized contract, whose concrete method signature lives in `docs/design/sis/Phase-4.6`.

## 1. Purpose

Enables Admission's Confirm Enrollment orchestration (FR-02, per Phase 6 Revision 2) to cause a `Student` stub to be persisted inside SIS, without Admission ever depending on SIS's Model or Entity classes directly (Company Development Standard §1.1/§11 cross-module rule).

## 2. Ownership of the interface

**SIS.** `StudentService::createStudentStub` (Phase 4.6).

## 3. Which module calls it

**Admission** — Confirm Enrollment orchestration (Phase 6 Revision 2 §2/§6), at the point corresponding to FR-02 §10 Main Flow step 5. Called as a direct internal method call, not a public API request (ADR-004 §3) — both modules run in the same application, same request, same database transaction.

## 4. Which module implements it

**SIS.**

## 5. Responsibilities delegated to SIS

| Responsibility | Belongs to |
|---|---|
| Persisting the `Student` stub | **SIS** (ADR-003) |
| Enforcing BR-SIS-002 during that persistence | **SIS** (Phase 4.3) |

## 6. Responsibilities explicitly NOT delegated to SIS

| Responsibility | Belongs to |
|---|---|
| Seat capacity (BR-ADM-001) / RTE quota ceiling (BR-ADM-003) re-validation | **Admission** |
| Applicant identity/duplicate check (FR-05a, BR-ADM-006) | **Admission** |
| Admission Number generation | **Admission** |
| `Application.status` transition to `Admitted` | **Admission** |

## 7. Required input — finalized (ADR-004 §3)

`application_id`, `admission_number`, `full_name`, `dob`, `category` (all required); `aadhaar_number` (optional). `full_name`/`dob`/`category`/`aadhaar_number` are copied by Admission directly from the `Application` being confirmed — Admission already holds these values, no additional lookup required. `section_id` and `medical_info` are explicitly **not** part of the input (ADR-004 §1) — both remain `NULL` until FR-06 profile completion. See `docs/design/sis/Phase-4.4`'s `StudentStubData` for the exact validated shape.

## 8. Expected output — finalized (ADR-004 §3)

The created `Student`'s `student_id`, plus its persisted field values (`StudentResponse`, Phase 4.4).

## 9. Error responsibilities

| Scenario | Belongs to |
|---|---|
| Rejecting confirmation before this interface is called (capacity breach, duplicate identity) | **Admission** |
| Detecting/signaling failure of the persistence step itself | **SIS** — an exception from `createStudentStub` propagates to Admission's transaction (§10), which rolls back |
| Compensating behavior on Admission's side if the SIS-side call fails after Admission-side state changes | **Resolved (ADR-004 §5): none needed.** The shared transaction (§10) rolls back atomically; there is no post-hoc state to compensate for. |

## 10. Transaction responsibility across the module boundary — finalized (ADR-004 §5)

A single local database transaction, opened and committed by Admission's Confirm Enrollment method, spans both modules' work for this operation. `createStudentStub` does not open its own transaction — it participates in the caller's. This is possible because Admission's and SIS's tables share one MySQL schema (Company Development Standard §4.1); no distributed transaction or saga pattern is needed.

## 11. Dependency direction

Admission's Service layer → SIS's Service method (ADR-003; Company Development Standard §1.1 no-cycle rule). SIS does not depend back on Admission for this interaction.

## 12. Open items

None. Every item Revision 1 tracked is resolved by ADR-004.

## Consistency review

Checked against the full approved baseline (ADR-001 through ADR-004, DG-SIS-001 Resolved, Phase 4.2–4.7 Revision 2, Phase 4 Closure Report Revision 2, Phase 5 Revision 2, Phase 6 Revision 2). No contradiction found.
