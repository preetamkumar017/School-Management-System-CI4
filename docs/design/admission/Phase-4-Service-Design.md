---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, Phase 3, Phase 6 (Confirm Enrollment — separate, already approved)
---

# Phase 4 — Admission Service Design (core CRUD; excludes FR-02 Confirm Enrollment, see Phase 6)

## `ApplicationService`

| Operation | Status | Reason |
|---|---|---|
| `createApplication(CreateApplicationRequest $request): ApplicationResponse` | Approved | Validates `class_applied_id` against Academic's `ClassService`, generates `application_reference_no`, sets `status = SUBMITTED`. |
| `verifyApplication(int $id, ApplicationVerifyRequest $request): ApplicationResponse` | Approved | `SUBMITTED → VERIFIED`. |
| `shortlistApplication(int $id, ApplicationShortlistRequest $request): ApplicationResponse` | Approved | `VERIFIED → SHORTLISTED`. |
| `waitlistApplication(int $id, ApplicationWaitlistRequest $request): ApplicationResponse` | Approved | `VERIFIED/SHORTLISTED → WAITLISTED`. |
| `rejectApplication(int $id, ApplicationRejectRequest $request): ApplicationResponse` | Approved | Any pre-`ADMITTED` status `→ REJECTED`; sets `decided_at`. |
| `getApplication(int $id): ApplicationResponse` | Approved | Plain read. |
| `listApplications(string $status = null, int $classId = null): array` | Approved | Paginated (Transaction-classified data). |

**The `SUBMITTED/VERIFIED/... → ADMITTED` transition is not an operation of this Service Design** — it is FR-02 Confirm Enrollment, designed separately in `docs/design/admission/Phase-6-Service-Design-Confirm-Enrollment.md`, which remains the authority for that transition, the Admission Number generation, and the SIS stub-creation call. This document does not duplicate or revise that design.

## `SeatAllocationService`

| Operation | Status | Reason |
|---|---|---|
| `createSeatAllocation(CreateSeatAllocationRequest $request): SeatAllocationResponse` | Approved | Validates `(class_id, academic_session_id)` uniqueness and `rte_quota_capacity ≤ 25%` of `total_capacity`. |
| `updateCapacity(int $id, UpdateSeatAllocationCapacityRequest $request): SeatAllocationResponse` | Approved | Re-validates the RTE-percentage rule against the new `total_capacity`; rejects a reduction below current `seats_filled`. |
| `getSeatAllocation(int $id): SeatAllocationResponse` | Approved | Plain read — this is the method Phase 6's Confirm Enrollment orchestration calls (via this Service, never `SeatAllocationModel` directly) to re-validate capacity/RTE ceiling at confirmation time. |
| `findForClassAndSession(int $classId, int $academicSessionId): ?SeatAllocationResponse` | Approved | Resolves the applicable `SeatAllocation` row for a given `Application.class_applied_id` against the current academic session. |

### Decision: concurrency strategy for `incrementSeatsFilled`

**Pessimistic row-level locking** (`SELECT ... FOR UPDATE` within the confirming transaction), not optimistic — this is exactly the "safety-critical capacity/seat-allocation race" case the Company Development Standard's concurrency rule (§4, common-columns/locking guidance) names as the documented exception to the default optimistic-locking rule. Two concurrent Confirm Enrollment calls for the last open seat in a class must never both succeed; a `version`-column optimistic check would let both reads pass before either write, then fail one **after** SIS has already been called for the stub-creation side effect — undoing that would be exactly the compensating-behavior problem ADR-004 §5 resolves a different way (a single shared transaction). Locking before calling into SIS avoids ever creating that problem in the first place. This is called from within `AdmissionService`'s Confirm Enrollment orchestration (Phase 6), not from this Service's own operations — `SeatAllocationService` exposes `getSeatAllocation` for the pre-check and `ApplicationModel`/`SeatAllocationModel`'s `incrementSeatsFilled` is invoked as part of Phase 6's transaction, not as a separate public method on this Service.

## Cross-module exposure

Both Services are called by other modules only through their public methods — Admission's own Phase 6 calls `SeatAllocationService`/`ApplicationModel` internally (same module, not cross-module); SIS never calls into Admission for this interaction (ADR-003). Academic's `ClassService`/`AcademicSessionService` are called by Admission, never the reverse.
