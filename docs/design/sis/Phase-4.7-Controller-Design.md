---
status: Approved (Revision 2) — fully finalized
last-updated: 2026-08-06
references: Phase 4.2 Revision 2 through Phase 4.6 Revision 2, ADR-003, ADR-004, DG-SIS-001 (Resolved)
---

# Phase 4.7 — SIS Controller Design (Revision 2)

Convention: one CI4 Controller per aggregate, extending `App\Core\BaseController`, base path `/api/v1/sis/...`; `POST` creation, `PATCH .../{id}` generic edits, `POST .../{id}/<sub-action>` dedicated transitions; every response wrapped in the standard response envelope (Company Development Standard §7); CI4 Validation rules are the Controller's only validation responsibility; exceptions map to HTTP status via the shared `App\Core\Exceptions` handlers.

## `StudentController` — base path `/api/v1/sis/students`

| Endpoint | Method / URI | Service method | Status |
|---|---|---|---|
| ~~Create student~~ | — | — | **Removed (ADR-004 §3)** — no public create endpoint exists. `StudentService::createStudentStub` is called only from Admission's Confirm Enrollment orchestration, never from a client-facing route on this Controller. |
| Update student | `PATCH /{id}` | `updateStudent(int, UpdateStudentRequest)` | Approved. |
| Transfer section | `POST /{id}/section-transfer` | `transferSection(int, StudentSectionTransferRequest)` | Approved (ADR-004 §4) — serves both first-assignment and later transfer. |
| Change status | `POST /{id}/status` | `changeStatus(int, StudentStatusChangeRequest)` | Approved (ADR-004 §1, §3). |
| Get student | `GET /{id}` | `getStudent(int)` | Approved. |
| List students by section | `GET /?section_id={sectionId}` | `listStudentsBySection(int)` | Approved. |

## `GuardianController` — base path `/api/v1/sis/guardians`

All endpoints (`POST /`, `PATCH /{id}`, `GET /{id}`) — **Approved**. `GuardianService`/DTOs/mapper unaffected by every revision to date.

## `StudentGuardianLinkController` — base path `/api/v1/sis/student-guardian-links`

All endpoints (`POST /`, `DELETE /{studentId}/{guardianId}`, `GET /by-student/{studentId}`) — **Approved**. `StudentGuardianLinkService`/DTOs/mapper unaffected by every revision to date.

## Routing / binding

`StudentController` has one fewer route than earlier drafts assumed (no create endpoint, per ADR-004 §3) — this is a deliberate decision, not an oversight; a future ADR would be needed to add one back. Controller responsibility (validate input, delegate to exactly one Service method, no Business Rule logic) is unchanged as a principle across every endpoint.

## Conclusion

**Fully finalized.** Every endpoint across all three Controllers is Approved. `StudentController`'s create route is deliberately absent, not pending.
