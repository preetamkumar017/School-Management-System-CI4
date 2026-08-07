---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, Phase 2, ADR-009
---

# Phase 3 — Transport Service and Controller Design

## `VehicleService`

Plain CRUD (create/update/get/list), `registration_no` uniqueness. No
delete — Master data.

## `RouteService`

| Operation | Reason |
|---|---|
| `createRoute(CreateRouteRequest): RouteResponse` | `route_name` uniqueness; if `vehicle_id` set, validates it exists and `capacity <= Vehicle.capacity` (BR-TRN-001, `ROUTE_CAPACITY_EXCEEDS_VEHICLE`). |
| `updateRoute(int $id, UpdateRouteRequest): RouteResponse` | Same capacity check as create. |
| `getRoute(int $id): RouteResponse` | Plain read. |
| `listRoutes(): array` | Plain list. |

## `TransportAllocationService`

| Operation | Reason |
|---|---|
| `allocate(AllocateTransportRequest): TransportAllocationResponse` | Validates `student_id` (SIS); validates `stop_name` is one of `Route.stops_json`; validates `emergency_contact` is 10-digit numeric (BR-TRN-004). Inside one transaction: `RouteModel::findForUpdate()` locks the `Route` row, `existsActiveByStudentId()` rejects `STUDENT_ALREADY_ALLOCATED` (BR-TRN-002), `countActiveByRouteId() >= Route.capacity` rejects `ROUTE_CAPACITY_EXCEEDED` (BR-TRN-001) — otherwise inserts with `status = Active`. Same concurrency-safe row-lock shape as Admission's `SeatAllocationModel::incrementSeatsFilled` (Stage 4). |
| `deallocate(int $id): TransportAllocationResponse` | `status → De-allocated`, freeing route capacity for the next allocation. |
| `getAllocation(int $id): TransportAllocationResponse` | Plain read. |
| `listByRoute(int $routeId): array` | Route roster. |
| `getActiveAllocationForStudent(int $studentId): ?TransportAllocationResponse` | ADR-014 §1 (BR-FEE-003) — read-only lookup Fees' `InvoiceService::generateInvoice` calls to fold a route-tier `FeeStructure` into the invoice total. |
| `changeRoute(int $id, ChangeRouteRequest): TransportAllocationResponse` | ADR-014 §1 (BR-TRN-005) — BR-TRN-005's route-change mutator, previously missing entirely. Same capacity/stop checks as `allocate()`; after committing, explicitly triggers `InvoiceService::recalculateForRouteChange()` — Transport pushing the fact into Fees, not the reverse. |

## Controllers — base path `/api/v1/transport/...`

| Controller | Base path | Endpoints |
|---|---|---|
| `VehicleController` | `/vehicles` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` |
| `RouteController` | `/routes` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` |
| `TransportAllocationController` | `/allocations` | `POST /` (allocate), `POST /{id}/deallocate`, `POST /{id}/change-route` (ADR-014 §1), `GET /{id}`, `GET /?route_id` |

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-009's
resolutions. BR-TRN-003/005/006 are explicitly out of scope, not
silently missing. BR-TRN-001's capacity ceiling gets a genuine
concurrency-safe implementation, matching this codebase's one prior
precedent for real row-lock enforcement (Admission's `SeatAllocation`).
