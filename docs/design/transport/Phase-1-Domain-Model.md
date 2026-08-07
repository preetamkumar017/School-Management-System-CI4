---
status: Approved (Original)
last-updated: 2026-08-07
references: Appendix-G Data Dictionary v1.0 (TRN module entities), Appendix-C v1.1 (BR-TRN-001–006), ADR-009
---

# Phase 1 — Transport Domain Model

## Scope

Per ADR-009: `Transport` (`App\Modules\Transport`) owns three entities:
`Route`, `Vehicle` (both Master), `TransportAllocation` (Transaction).
BR-TRN-003 (GPS live tracking), BR-TRN-005 (route-change fee
recalculation), and BR-TRN-006 (driver/trip validity) are out of scope
(ADR-009 §11, §13, §14).

## Entity: `Vehicle` (ENT-TRN-002, table `vehicles`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| registration_no | VARCHAR(20) | N | – | Unique |
| capacity | INT | N | – | Positive |
| gps_device_id | VARCHAR(50) | Y | NULL | Stored only, no live-feed behavior (ADR-009 §11) |
| license_valid_until | DATE | Y | NULL | Stored only, no trip-start gate against it (ADR-009 §14) |

Unique constraint: `registration_no`. Documented before `Route` since
`Route.vehicle_id` references it.

### Lifecycle

Created (registered) → Active → Maintenance → Decommissioned.

---

## Entity: `Route` (ENT-TRN-001, table `routes`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| route_name | VARCHAR(50) | N | – | Unique |
| stops_json | JSON | N | – | Non-empty array |
| capacity | INT | N | – | Positive; ≤ `Vehicle.capacity` when `vehicle_id` is set (BR-TRN-001) |
| vehicle_id | BIGINT UNSIGNED | Y | NULL | Decided additive column (ADR-009 §8) — FK → `vehicles` (intra-module, real FK) |

Unique constraint: `route_name`.

### Lifecycle

Created (configured) → Active → Modified (stops/capacity changes) →
Deactivated.

---

## Entity: `TransportAllocation` (ENT-TRN-003, table `transport_allocations`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| student_id | BIGINT UNSIGNED | N | – | FK → SIS's `students` (cross-module, plain FK, validated via `StudentService`) |
| route_id | BIGINT UNSIGNED | N | – | FK → `routes` (intra-module, real FK) |
| stop_name | VARCHAR(50) | N | – | Must be one of `Route.stops_json` |
| emergency_contact | VARCHAR(10) | N | – | 10-digit numeric, mandatory (BR-TRN-004) |
| status | enum (`Active`, `Waitlisted`, `De-allocated`) | N | Active | BR-TRN-001, BR-TRN-002 |

No DB-level unique constraint for "one Active allocation per student"
(ADR-009 §10 — Service-layer check inside a locked transaction instead,
MySQL has no native partial unique index).

### Lifecycle

Created (Allocated, capacity + single-route gate, BR-TRN-001/002) →
Active → De-allocated/Changed → Archived.

## Out of scope

- BR-TRN-003 GPS update interval compliance (ADR-009 §11 — no live
  ingestion pipeline).
- BR-TRN-005 Route change fee recalculation (ADR-009 §13 — reopens
  ADR-007 §3 from the Fees side too, not closed here).
- BR-TRN-006 Driver/vehicle trip-start validity (ADR-009 §14 — no
  `Driver`/`Trip` entity in Appendix-G).
  **Superseded 2026-08-07**: `Driver` and `Trip` are designed and
  implemented per ADR-019 and
  `docs/design/transport/Phase-4-Driver-Trip-Design.md`. The external
  licensing-data-source integration named in BR-TRN-006's own
  Precondition remains out of scope (ADR-019 §4).
