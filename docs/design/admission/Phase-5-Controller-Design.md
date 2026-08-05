---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 through Phase 4
---

# Phase 5 — Admission Controller Design (core CRUD; excludes FR-02 Confirm Enrollment, see Phase 6)

Convention: one CI4 Controller per aggregate, extending `App\Core\BaseController`, base path `/api/v1/admission/...`; every response wrapped in the standard response envelope (Company Development Standard §7).

## `ApplicationController` — base path `/api/v1/admission/applications`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create application | `POST /` | `createApplication(...)` |
| Verify | `POST /{id}/verify` | `verifyApplication(int, ...)` |
| Shortlist | `POST /{id}/shortlist` | `shortlistApplication(int, ...)` |
| Waitlist | `POST /{id}/waitlist` | `waitlistApplication(int, ...)` |
| Reject | `POST /{id}/reject` | `rejectApplication(int, ...)` |
| Get application | `GET /{id}` | `getApplication(int)` |
| List applications | `GET /?status={status}&class_id={classId}` | `listApplications(?string, ?int)` |

The Confirm Enrollment endpoint (`Application → ADMITTED`) is defined in Phase 6, not here — this Controller does not expose an `ADMITTED` transition.

## `SeatAllocationController` — base path `/api/v1/admission/seat-allocations`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create allocation | `POST /` | `createSeatAllocation(...)` |
| Update capacity | `PATCH /{id}` | `updateCapacity(int, ...)` |
| Get allocation | `GET /{id}` | `getSeatAllocation(int)` |
| Find for class/session | `GET /?class_id={classId}&academic_session_id={sessionId}` | `findForClassAndSession(int, int)` |

All **Approved**.

## API catalogue note

None of these routes exist yet in Appendix-K (API Specification) — same gap ADR-001 flagged for Academic. Captured in `docs/design/School-ERP-API-Governance-Supplement.md` as an addendum until Appendix-K itself is regenerated.
