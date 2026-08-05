---
status: Approved (Revision 2) — fully finalized
last-updated: 2026-08-06
references: Phase 4.2 Revision 2, Phase 4.3, Phase 4.4 Revision 2, Phase 4.5 Revision 2, ADR-003, ADR-004, DG-SIS-001 (Resolved)
---

# Phase 4.6 — SIS Service Design (Revision 2)

Revision 1 was blocked on ADR-003's dependency-direction change and DG-SIS-001. ADR-004 resolves both remaining Open items; this revision finalizes the design.

## `StudentService`

| Operation | Status | Reason |
|---|---|---|
| `createStudentStub(StudentStubData $data): StudentResponse` | **Finalized (ADR-004)** | Called only from `AdmissionService`'s Confirm Enrollment orchestration (Admission module), within the single database transaction ADR-004 §5 establishes — never from a public SIS endpoint. Maps `application_id`, `admission_number`, `full_name`, `dob`, `category`, `aadhaar_number` via `StudentMapper::toEntityFromStub`; persists with `status = DRAFT`, `section_id = NULL`, `medical_info = NULL`. Returns the created `StudentResponse` including `student_id`. |
| `updateStudent(int $id, UpdateStudentRequest $request): StudentResponse` | Unaffected, valid | Operates on an already-existing `Student`. |
| `transferSection(int $id, StudentSectionTransferRequest $request): StudentResponse` | **Finalized (ADR-004 §4)** | Serves both a stub's first `section_id` assignment (during FR-06 profile completion, while `status = DRAFT`) and any later transfer (while `status = ACTIVE`, BR-SIS-005 capacity check applies identically either way). |
| `changeStatus(int $id, StudentStatusChangeRequest $request): StudentResponse` | **Finalized (ADR-004 §1, §3)** | `DRAFT → ACTIVE` now performs a well-defined BR-SIS-003 check: `full_name`, `dob`, `category`, `admission_number`, `section_id` all non-null (all except `section_id` are guaranteed non-null since stub creation; `section_id` is checked here specifically since it's the one field FR-06 completion is responsible for). BR-SIS-006 guardian check (`existsByStudentId`) unaffected. |
| `getStudent(int $id): StudentResponse` | Unaffected, valid | Plain read. |
| `listStudentsBySection(int $sectionId): array` | Unaffected, valid | Queries existing rows by a supplied, concrete `section_id`. |

## `GuardianService` — all Unaffected

`createGuardian`, `updateGuardian`, `getGuardian` — intra-module only (ADR-002); unaffected by every revision to date.

## `StudentGuardianLinkService` — all Unaffected

`linkGuardian`, `unlinkGuardian`, `listGuardiansForStudent` — unaffected by every revision to date.

## Cross-module dependency — finalized (ADR-004 §5)

SIS does not depend on Admission (confirmed, unchanged from Revision 1 — the originally-drafted reverse direction was never adopted). Admission's `AdmissionService` depends on `StudentService::createStudentStub` and `StudentService::changeStatus` is called by SIS's own Controller, never by Admission — Admission's involvement ends once `createStudentStub` returns within the shared transaction (ADR-004 §5). No cross-module dependency exists in the SIS → Admission direction.

## Service transactions — finalized (ADR-004 §5)

`createStudentStub` does not open its own transaction — it participates in the transaction `AdmissionService`'s Confirm Enrollment method opens, per ADR-004 §5 (single local transaction, no distributed transaction or compensating-action pattern). Every other operation on this page follows the Company Development Standard's default: the Service method that owns the operation opens and commits its own transaction.

## Conclusion

**Fully finalized.** ADR-004 resolves both items Revision 1 left Open (`createStudentStub`, `changeStatus`'s BR-SIS-003 portion) and the cross-module dependency question. Every operation across all three services is now Finalized or Unaffected.
