---
status: Approved (Original)
last-updated: 2026-08-07
references: Appendix-E FR-40–42, Appendix-C BR-RPT-001–005, ADR-010
---

# Phase 1 — Reports Service and Controller Design

No entities, no Model, no DTO layer of persisted request/response
shapes beyond the summary response itself — Reports is a pure
composition layer over other modules' Services, per
`docs/design/School-ERP-Module-Architecture.md`'s own description and
ADR-010 §7/§8.

## `SummaryResponse` (DTO)

Plain response object, not backed by any entity.

| Field | Source |
|---|---|
| generated_at | `Time::now()` at request time (BR-RPT-005) |
| total_users | `Config\Services::userService()->listUsers()` count |
| total_classes | `Config\Services::classService()->listClasses()` count |
| total_academic_sessions | `Config\Services::academicSessionService()->listSessions()` count |
| total_departments | `Config\Services::departmentService()->listDepartments()` count |
| total_designations | `Config\Services::designationService()->listDesignations()` count |
| total_employees | `Config\Services::employeeService()->listEmployees()` count |
| total_books | `Config\Services::bookService()->listBooks()` count |
| books_available | Same list, filtered `is_available = true` |
| total_vehicles | `Config\Services::vehicleService()->listVehicles()` count |
| total_routes | `Config\Services::routeService()->listRoutes()` count |
| total_fee_heads | `Config\Services::feeHeadService()->listFeeHeads()` count |

## `ReportsService`

| Operation | Reason |
|---|---|
| `getSummary(): SummaryResponse` | Calls the ten list-all methods above (each already-existing on its owning module's Service — Reports adds no new query method to any other module, ADR-010 §7) and counts each result. |

## Controller — base path `/api/v1/reports/...`

| Controller | Base path | Endpoints |
|---|---|---|
| `ReportsController` | `/summary` | `GET /` |

## Conclusion

Deliberately the smallest possible honest slice of FR-40 — a real,
role-agnostic (BR-RPT-001 not enforced, matching this codebase's
consistent pre-existing posture) master-data summary, composed
exclusively from Service methods every other module already exposes.
FR-41 (custom report builder), FR-42 (trend analytics), Excel/PDF
export, and BR-RPT-002/003/004 are explicitly out of scope (ADR-010
§8) — a future dedicated Reports pass is the right place to add real
per-module aggregate methods once genuine dashboard requirements are
scoped, not this one, speculatively, across five modules at once.

## Extended by ADR-022 (2026-08-08) — four real report areas, PDF/Excel export

`docs/ADR/ADR-022-reports-dashboard.md` is the "future dedicated Reports
pass" this doc's Conclusion pointed at. `ReportsService` gained four
methods (`getFeeCollectionSummary`, `getAttendanceOverview`,
`getAdmissionsFunnel`, `getAcademicPerformance`), each with its own DTO
and `ReportsController` endpoint, plus `renderPdf()`/`renderExcel()`
composed into a `/pdf`/`/excel` variant of every area. `getSummary()`
above is untouched. See ADR-022 for the full per-area aggregate-method
inventory, the reused-not-recomputed guarantees (GPA/class-rank/
attendance-threshold/`Invoice.total_amount`), and why PDF/Excel exports
are streamed directly rather than persisted via `DocumentService`
(Reports still has no owning entity, ADR-010 §7 unreversed).
