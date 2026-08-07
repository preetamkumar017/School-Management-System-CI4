---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-018 record)
relates-to: Appendix-C BR-TRN-006 (Driver/Vehicle Assignment Validity), FR-30/FR-31; ADR-009 §14 (named BR-TRN-006 out of scope — "Needs `Driver` and `Trip` entities, neither of which exists anywhere in Appendix-G's Transport catalogue... a future design pass scoping `Driver`/`Trip` entities is the prerequisite"); ADR-009 §8 (`Route.vehicle_id`, the additive-FK precedent this ADR reuses); ADR-011 §5 (listed BR-TRN-006 among the fourteen items that never became a scalar constant)
---

# ADR-019: Transport `Driver`/`Trip` entities and trip-start validity (BR-TRN-006)

## Context

Appendix-C's BR-TRN-006 says a trip cannot be logged as started unless
both a valid driver and a currently licensed vehicle are assigned to
that route, blocking with the specific expired/missing credential
identified when either check fails. ADR-009 §14 declined to build this
because no `Driver` or `Trip` entity exists anywhere in Appendix-G's
Transport catalogue (only `Route`/`Vehicle`/`TransportAllocation`) — the
same "no approved entity, don't invent one" posture ADR-013
(`Substitution`) and ADR-016 (`Application.hold_expires_at`) both later
crossed for the same reason: a Business Rule whose own Trigger/
Precondition/Post-condition fields describe concrete, structured
behavior that no existing entity or scalar `Configuration` row can hold.
`Vehicle.license_valid_until` already exists, per Appendix-G (ADR-009
§1), but nothing gated a trip-start workflow against it — there was no
trip-start workflow at all.

Appendix-E's FR-31 (GPS-Based Live Bus Tracking) independently confirms
`Driver` and `Trip` as real domain concepts this project already
recognizes at the requirements layer: its actor list names `Driver`
alongside `Admin Staff`/`Transport Coordinator`/`Parent`/`System`, and
its own Main Flow step 1 is "Driver starts the trip" — the identical
trigger BR-TRN-006 names. FR-31's remaining steps (GPS transmission,
live tracking feed, parent portal view) are the GPS-live-tracking half
ADR-009 §11 already separately deferred and this ADR does not reopen;
only the trip-start step is in scope here.

## Decision

### 1. `Driver` — a new Transport entity, Transport-Coordinator-maintained, same CRUD shape as `Vehicle`

`drivers` (`driver_id`, `full_name`, `license_number`,
`license_valid_until`, `status` [`Active`/`Inactive`], plus the standard
audit/soft-delete columns). A Transport Coordinator enters and maintains
`license_number`/`license_valid_until` directly — the same shape as
`Vehicle.license_valid_until` itself (ADR-009 §1, self-reported/stored,
no verification integration), `ScholarshipWaiver`'s Finance-Team-entered
`waiver_type`, and `deductions_json`'s caller-supplied HR/Finance input
(ADR-008 §8). `status` lets a Coordinator mark a driver `Inactive`
(resigned, suspended, on long leave) without deleting their record —
useful, bounded scope: it is checked at trip-start (§5) alongside
license validity, both being facets of "a valid driver," not a
speculative addition.

### 2. `Trip` — a new Transport entity, one row per trip-start event

`trips` (`trip_id`, `route_id`, `driver_id`, `vehicle_id`, `started_at`,
`status`, plus the standard audit/soft-delete columns). `route_id`,
`driver_id`, `vehicle_id` are all real intra-module FKs (`RESTRICT` on
delete/update — a trip is a historical log entry, its driver/vehicle/
route must not disappear out from under it). `driver_id`/`vehicle_id`
are captured onto the `Trip` row at start time (copied from the Route's
assignment, §3) rather than re-derived later — the row is the durable
record of *who* and *what* actually ran that trip, even if the Route's
own assignment changes afterward.

`status` is a single-value enum, `Started`, today. BR-TRN-006's own
Post-condition is "Trip is logged as started only when both checks
pass" — it says nothing about trip completion, cancellation, or duration
tracking, and Appendix-G's Transport catalogue has no `Trip` card to
draw a fuller lifecycle from. Modeling `Completed`/`Cancelled` states
here would be inventing lifecycle Appendix-C never asked for — a trip's
existence *is* the "trip was started" log this BR requires; a future
pass can extend `status` if a real requirement for trip completion
tracking is scoped. This is the same restraint ADR-009 §13 already
applied ("not something to invent here" for GPS ingestion) turned
inward on this ADR's own new entity.

### 3. Driver↔Route assignment: an additive `Route.driver_id` FK, mirroring `Route.vehicle_id` exactly — not a per-Trip assignment

BR-TRN-006's own Statement is explicit about where the assignment lives:
"a valid driver and a currently licensed vehicle are assigned **to that
route**" — not to a trip. `Route` already carries exactly this shape for
`Vehicle` (`vehicle_id`, ADR-009 §8, a nullable additive FK, decided
there because Appendix-G's Relationships section named a Route↔Vehicle
link with no attribute-catalogue column for it). A `Driver` needs the
identical link for the identical reason, so `Route.driver_id` (nullable
BIGINT, intra-module FK to `drivers`, `SET NULL`/`RESTRICT` — the same
delete/update behavior `vehicle_id`'s own FK already uses) is the
decided shape, not a per-`Trip` `driver_id` input the caller supplies at
start time. A per-trip assignment was considered and rejected: it would
let a trip be started with a driver who was never actually assigned to
that route at all, silently skipping the "assigned to that route" half
of the rule's own Statement — the Route-level FK is what makes
"assigned" a real, checkable fact rather than caller-asserted trust.
`TripService::startTrip(int $routeId)` therefore takes only a `route_id`
— the driver and vehicle are read off the Route's own assignment, not
supplied by the caller, so there is no way to start a trip with a
driver/vehicle pair that was never actually assigned.

`RouteService::createRoute`/`updateRoute` gain the identical existence
check `assertCapacityWithinVehicle`'s sibling already established for
`vehicle_id`: assigning a `driver_id` that doesn't exist throws
`DRIVER_NOT_FOUND` at assignment time, not silently accepted and only
discovered later at trip-start.

### 4. Out of scope, explicitly: the external licensing-data-source integration

BR-TRN-006's own Precondition names "Driver and vehicle licensing/
validity data are on file (**Client/Product Decision Required for the
licensing data source/integration**)" as a separate decision from the
rule's core logic. That clause is about verifying `license_number`/
`license_valid_until` against an external authority — e.g. a government
RTO/DMV licensing database API — which no vendor has been chosen for,
matching this project's consistent restraint on unchosen external
integrations (the SMS/Email/Push gateway, ADR-010 §1/§2/§5; GPS device
ingestion, ADR-009 §11). This ADR does **not** build or stub any such
integration. What it does build is the in-house half: a Transport
Coordinator enters and maintains `license_number`/`license_valid_until`
directly on file (§1), and the system gates trip-start against that
stored data (§5) — the same "self-reported/stored, no verification
integration" posture `Vehicle.license_valid_until` already established
under ADR-009 §1, now applied symmetrically to `Driver`.

### 5. `TripService::startTrip(int $routeId): TripResponse` — the enforcement point, checks in the order the BR's own fields imply

1. Route must exist (`ROUTE_NOT_FOUND`, the existing shared code every
   other Transport Service already throws).
2. `Route.driver_id` must be set (`DRIVER_NOT_ASSIGNED_TO_ROUTE`) — no
   driver assigned to the route at all.
3. `Route.vehicle_id` must be set (`VEHICLE_NOT_ASSIGNED_TO_ROUTE`) —
   symmetric with step 2; `vehicle_id` has been optional since ADR-009
   §8, so a Route can exist with no vehicle assigned yet.
4. The assigned `Driver`'s `status` must be `Active`
   (`DRIVER_INACTIVE`) — a Coordinator-flagged fact about the driver
   themselves, checked alongside license validity as another facet of
   "a valid driver."
5. The assigned `Driver`'s `license_valid_until`: `DRIVER_LICENSE_MISSING`
   if null, `DRIVER_LICENSE_EXPIRED` if set but before today.
6. The assigned `Vehicle`'s `license_valid_until`: `VEHICLE_LICENSE_MISSING`
   if null, `VEHICLE_LICENSE_EXPIRED` if set but before today.
7. Only if every check above passes: insert the `Trip` row
   (`route_id`, `driver_id`/`vehicle_id` copied from the Route,
   `started_at = Time::now()`, `status = Started`), audited via
   `AuditService::record()`, and return it.

Six distinct, stable error codes (not one generic
`DRIVER_OR_VEHICLE_INVALID`) is a direct read of BR-TRN-006's own
Exception Handling field: "Trip-start blocked with the specific
expired/missing credential identified." A caller (or the Transport
Coordinator resolving the flag, per the BR's own Owner field) needs to
know *which* of driver-missing/driver-inactive/driver-expired/
vehicle-missing/vehicle-expired/vehicle-not-assigned failed, not just
that something did.

No row lock is needed here, unlike `RouteModel::findForUpdate` under
BR-TRN-001/002 (ADR-009 §9/§10): those guard a shared, contended counter
(seats filled against a capacity ceiling) where two concurrent requests
racing matters. Starting a trip reads a Route's own assignment and
inserts one new `Trip` row — no shared counter is being incremented, so
a plain `find()` is sufficient.

## Consequences

- New migrations: `2026-08-07-240001_CreateDriversTable.php`,
  `2026-08-07-240002_CreateTripsTable.php`,
  `2026-08-07-240003_AddDriverIdToRoutesTable.php` (additive nullable
  `Route.driver_id`, `SET NULL`/`RESTRICT` FK to `drivers`, mirroring
  `vehicle_id`'s own migration exactly).
- `Driver` entity/`DriverModel`/`DriverService`
  (`createDriver`/`updateDriver`/`getDriver`/`listDrivers`)/
  `DriverController`, `CreateDriverRequest`/`UpdateDriverRequest`/
  `DriverResponse` DTOs — new Transport module surface, following
  `Vehicle`'s established CRUD shape exactly.
- `Trip` entity/`TripModel`/`TripService`
  (`startTrip`/`getTrip`/`listByRoute`)/`TripController`,
  `TripResponse` DTO.
- `Route` entity/`RouteModel`/`RouteService`/`CreateRouteRequest`/
  `UpdateRouteRequest`/`RouteResponse` gain `driver_id`, mirroring every
  place `vehicle_id` already appears.
- New endpoints: `POST /transport/drivers`, `PATCH
  /transport/drivers/{id}`, `GET /transport/drivers/{id}`, `GET
  /transport/drivers`, `POST /transport/trips/start` (body:
  `route_id`), `GET /transport/trips/{id}`, `GET
  /transport/trips?route_id=`.
- `docs/design/transport/Phase-1-Domain-Model.md`'s BR-TRN-006
  "out of scope" bullet gets a superseded note, matching ADR-013's
  precedent for Timetable's Phase-1 doc. A new
  `docs/design/transport/Phase-4-Driver-Trip-Design.md` documents the
  two new entities, matching `docs/design/timetable/
  Phase-4-Substitution-Design.md`'s precedent.
- `docs/development/School-ERP-Development-Roadmap.md`'s "Immediate next
  action" section is updated: BR-TRN-006 removed from the remaining
  Appendix-C items list, a new Stage 15 entry added.
- `docs/ADR/ADR-009-library-and-transport-scope-decisions.md` §14's note
  is corrected to point here, matching the precedent already set there
  for BR-TT-004 (ADR-013), BR-ADM-007/008 (ADR-016), and BR-LIB-006
  (ADR-017).
- `docs/ADR/ADR-011-configuration-entity-scope-decisions.md`'s
  BR-TRN-006 mention is corrected inline, matching the precedent already
  set for the same three items above — noting this was never a
  `Configuration` candidate (a real entity/workflow build, not a scalar
  rate/threshold).
- The external licensing-data-source integration (§4) remains open —
  unchanged by this ADR, not silently expanded into. GPS live-tracking
  ingestion (ADR-009 §11) remains separately deferred and untouched.
