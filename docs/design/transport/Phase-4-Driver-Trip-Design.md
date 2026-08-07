---
status: Approved
last-updated: 2026-08-07
references: Appendix-C v1.1 BR-TRN-006, Appendix-E v1.0 FR-30/FR-31, ADR-019, ADR-009 §8/§14
---

# Phase 4 — Transport Driver/Trip Validity (BR-TRN-006)

## Scope

Follow-up pass, decided by ADR-019. Adds two entities to `Transport`:
`Driver` (ENT-TRN-004) and `Trip` (ENT-TRN-005), plus an additive
`Route.driver_id` column mirroring `Route.vehicle_id` (ADR-009 §8).

## Entity: `Driver` (ENT-TRN-004, table `drivers`)

Extends `App\Core\BaseEntity`. Net-new per ADR-019 §1 — no entity in
Appendix-G's Data Dictionary models a Driver, despite FR-31 naming
`Driver` as a real actor.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| full_name | VARCHAR(100) | N | – | – |
| license_number | VARCHAR(30) | N | – | Unique |
| license_valid_until | DATE | Y | NULL | Checked at trip-start (BR-TRN-006) |
| status | enum (`Active`, `Inactive`) | N | Active | Checked at trip-start (BR-TRN-006) |

Unique constraint: `license_number`. Transport-Coordinator-entered/
maintained, no external licensing-database verification (ADR-019 §4).

### Lifecycle

Created (registered) → Active → Inactive (resigned/suspended/on leave) →
Active again, if reinstated.

## Entity: `Trip` (ENT-TRN-005, table `trips`)

Extends `App\Core\BaseEntity`. One row per trip-start event.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| route_id | BIGINT UNSIGNED | N | – | FK → `routes` (intra-module, RESTRICT) |
| driver_id | BIGINT UNSIGNED | N | – | FK → `drivers` (intra-module, RESTRICT); copied from `Route.driver_id` at start time |
| vehicle_id | BIGINT UNSIGNED | N | – | FK → `vehicles` (intra-module, RESTRICT); copied from `Route.vehicle_id` at start time |
| started_at | DATETIME | N | – | `Time::now()` at trip-start |
| status | enum (`Started`) | N | Started | Single-valued today — see ADR-019 §2 |

### `Trip` Lifecycle

A trip-start event fires (driver app or scheduled time, per BR-TRN-006's
own Trigger) → `TripService::startTrip(route_id)` reads the Route's own
`driver_id`/`vehicle_id` assignment (§`Route` below) → both must be
assigned, the Driver must be `Active` with a currently valid license,
and the Vehicle must have a currently valid license, or the call is
rejected with the specific missing/expired credential identified
(ADR-019 §5) → only then is the `Trip` row inserted, logging the start.
No further transitions modeled this pass (ADR-019 §2) — trip completion/
cancellation tracking is a future extension if a real requirement for it
is scoped.

## `Route` gains `driver_id` (additive, ADR-019 §3)

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| driver_id | BIGINT UNSIGNED | Y | NULL | Decided additive column (ADR-019 §3) — FK → `drivers` (intra-module, `SET NULL`/`RESTRICT`, mirrors `vehicle_id` exactly) |

`RouteService::createRoute`/`updateRoute` reject an unknown `driver_id`
with `DRIVER_NOT_FOUND` at assignment time (mirrors the existing
`VEHICLE_NOT_FOUND` check for `vehicle_id`).

## Service layer

- `DriverService::createDriver()`, `::updateDriver()`, `::getDriver()`,
  `::listDrivers()` — same CRUD shape as `VehicleService`.
- `TripService::startTrip(int $routeId): TripResponse` — the BR-TRN-006
  enforcement point (ADR-019 §5). `::getTrip()`, `::listByRoute()`.

## Routes (base `/api/v1/transport`)

| Method | Path | Controller::method |
|---|---|---|
| POST | `drivers` | `DriverController::create` |
| PATCH | `drivers/{id}` | `DriverController::update` |
| GET | `drivers/{id}` | `DriverController::show` |
| GET | `drivers` | `DriverController::index` |
| POST | `trips/start` | `TripController::start` |
| GET | `trips/{id}` | `TripController::show` |
| GET | `trips?route_id=` | `TripController::index` |

## Out of scope

- The external licensing-data-source integration named in BR-TRN-006's
  own Precondition ("Client/Product Decision Required for the licensing
  data source/integration") — no vendor chosen, matching this project's
  restraint on unchosen external integrations (ADR-019 §4).
- GPS live-tracking ingestion (ADR-009 §11, FR-31's remaining Main Flow
  steps beyond "driver starts the trip") — separately deferred, not
  reopened here.
- Trip completion/cancellation lifecycle — BR-TRN-006's own
  Post-condition only covers the start event (ADR-019 §2).
