---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, ADR-009
---

# Phase 2 — Transport Model and DTO Design

Convention: `Route`/`Vehicle` are Master data (no pagination);
`TransportAllocation` is Transaction data, exposed via a scoped listing
(by route), matching prior modules' precedent.

## `VehicleModel`

| Method | Purpose |
|---|---|
| `findByRegistrationNo(string): ?Vehicle` / `existsByRegistrationNo(string): bool` / `...ExceptId(...)` | Business-key uniqueness |

## `RouteModel`

| Method | Purpose |
|---|---|
| `findByName(string): ?Route` / `existsByName(string): bool` / `...ExceptId(...)` | Business-key uniqueness |
| `findForUpdate(int $routeId): ?Route` | `SELECT ... FOR UPDATE` — the row lock BR-TRN-001/002 (ADR-009 §9/§10) is enforced against |

## `TransportAllocationModel`

| Method | Purpose |
|---|---|
| `countActiveByRouteId(int $routeId): int` | BR-TRN-001, read inside the locked transaction |
| `existsActiveByStudentId(int $studentId): bool` | BR-TRN-002, read inside the same locked transaction |
| `findByRouteId(int $routeId): array` | Route's allocation roster |

## DTOs

`CreateVehicleRequest`/`UpdateVehicleRequest`: `registration_no`
(create-only), `capacity`, `gps_device_id`, `license_valid_until`.
`VehicleResponse`: `vehicle_id`, `registration_no`, `capacity`,
`gps_device_id`, `license_valid_until`.

`CreateRouteRequest`/`UpdateRouteRequest`: `route_name` (create-only),
`stops_json`, `capacity`, `vehicle_id` (nullable). `RouteResponse`:
`route_id`, `route_name`, `stops_json`, `capacity`, `vehicle_id`.

`AllocateTransportRequest`: `student_id`, `route_id`, `stop_name`,
`emergency_contact`. `TransportAllocationResponse`:
`transport_allocation_id`, `student_id`, `route_id`, `stop_name`,
`emergency_contact`, `status`.
