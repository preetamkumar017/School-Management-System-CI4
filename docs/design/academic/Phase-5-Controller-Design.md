---
status: Approved (Revision 1) — fully finalized
last-updated: 2026-08-06
references: Phase 1 through Phase 4, ADR-004
---

# Phase 5 — Academic Controller Design

Convention: one CI4 Controller per aggregate, extending `App\Core\BaseController`, base path `/api/v1/academic/...`; every response wrapped in the standard response envelope (Company Development Standard §7); CI4 Validation rules are the Controller's only validation responsibility.

## `AcademicSessionController` — base path `/api/v1/academic/sessions`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create session | `POST /` | `createSession(...)` |
| Update session | `PATCH /{id}` | `updateSession(int, ...)` |
| Change status | `POST /{id}/status` | `changeStatus(int, ...)` |
| Get session | `GET /{id}` | `getSession(int)` |
| List sessions | `GET /` | `listSessions()` |
| Get current active session | `GET /current` | `getCurrentActiveSession()` |

## `ClassController` — base path `/api/v1/academic/classes`

`POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` — all **Approved**, plain CRUD per Phase 4.

## `SectionController` — base path `/api/v1/academic/sections`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create section | `POST /` | `createSection(...)` |
| Update section | `PATCH /{id}` | `updateSection(int, ...)` |
| Get section | `GET /{id}` | `getSection(int)` |
| List sections by class | `GET /?class_id={classId}` | `listSectionsByClass(int)` |

## `SubjectController` — base path `/api/v1/academic/subjects`

`POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` — all **Approved**, plain CRUD per Phase 4.

## `GradingSchemeController` — base path `/api/v1/academic/grading-schemes`

| Endpoint | Method / URI | Service method | Status |
|---|---|---|---|
| Create scheme | `POST /` | `createGradingScheme(...)` | Approved |
| Update scheme | `PATCH /{id}` | `updateGradingScheme(int, ...)` | Approved — returns the mutated row, or a Business Rule error (`GRADING_SCHEME_LOCKED_BY_CLOSED_EXAM`) directing the caller to `POST /` a new scheme instead (Phase 4) |
| Get scheme | `GET /{id}` | `getGradingScheme(int)` | Approved |
| List schemes | `GET /` | `listGradingSchemes()` | Approved |

## `ClassSubjectMapController` — base path `/api/v1/academic/class-subject-map`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Map subject to class | `POST /` | `mapSubjectToClass(...)` |
| Unmap | `DELETE /{classId}/{subjectId}` | `unmapSubjectFromClass(int, int)` |
| List subjects for class | `GET /by-class/{classId}` | `listSubjectsForClass(int)` |

All **Approved**.

## API catalogue note

None of these routes exist yet in Appendix-K (API Specification) — ADR-001 already flagged Appendix-K as missing an Academic API-group subsection. This table, together with `docs/design/School-ERP-API-Governance-Supplement.md`, is the addendum that fills that gap until Appendix-K itself is regenerated.

## Conclusion

**Fully approved, no Open endpoints.** Every endpoint across all six Controllers is ready for implementation.
