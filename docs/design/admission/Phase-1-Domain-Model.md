---
status: Approved (Original)
last-updated: 2026-08-06
references: Appendix-G Data Dictionary v1.0 (ADM module entities), ADR-003, ADR-004, Company Development Standard
---

# Phase 1 — Admission Domain Model

## Scope

`Admission` (`App\Modules\Admission`) owns two entities: `Application` and
`SeatAllocation`. This document covers their baseline domain model. The
FR-02 Confirm Enrollment orchestration sequence (the `Application → Admitted`
transition and its call into SIS) is covered separately in
`docs/design/admission/Phase-6-Service-Design-Confirm-Enrollment.md`,
already approved — this document does not repeat or revise it, only the
entities' baseline shape and their other lifecycle stages.

Field lists below are taken directly from Appendix-G's ADM module entity
cards.

---

## Entity: `Application` (ENT-ADM-001, table `applications`)

Extends `App\Core\BaseEntity` (surrogate `application_id`, `version`, common audit columns).

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| application_reference_no | VARCHAR(20) | N | – | Unique, system-generated (e.g. `APP-2026-10023`) — assigned at submission, distinct from the `admission_number` SIS assigns later at Confirm Enrollment (FR-02) |
| applicant_name | VARCHAR(100) | N | – | Non-empty; PII |
| dob | DATE | N | – | Past date, age-appropriate for `class_applied_id`; PII |
| class_applied_id | BIGINT UNSIGNED | N | – | FK → Academic's `classes` (cross-module, plain FK, validated via Academic's `ClassService` call, never a DB-level FK across the module boundary) |
| aadhaar_number | VARCHAR(12) | Y | NULL | 12-digit numeric, checksum-valid; BR-ADM-006; Sensitive PII |
| category | enum (`GENERAL`, `RTE`) | N | GENERAL | BR-ADM-003, BR-ADM-004 |
| status | enum (`SUBMITTED`, `VERIFIED`, `SHORTLISTED`, `WAITLISTED`, `ADMITTED`, `REJECTED`) | N | SUBMITTED | Forward-only transitions |
| submitted_at | DATETIME | N | CURRENT_TIMESTAMP | – |
| decided_at | DATETIME | Y | NULL | Timestamp of the approval/rejection decision |

Relationships: many-to-one with Academic's `Class`; one-to-one with SIS's `Student` (established upon confirmation, FR-02, per ADR-003 — Admission triggers, SIS persists). Child entity: `Document` (Administration module, uploaded application documents).

**Documentation gap, resolved (ADR-004 §6):** FR-02 §7's precondition references an "Approved" status that does not appear in this enum (`SUBMITTED`, `VERIFIED`, `SHORTLISTED`, `WAITLISTED`, `ADMITTED`, `REJECTED`) — confirmed again here against the authoritative Appendix-G attribute catalogue. Per ADR-004, "Approved" refers to either `SHORTLISTED` or `WAITLISTED` — both are on a positive track toward admission and FR-02 doesn't distinguish between them for this purpose.

### `Application` Lifecycle

Created (`SUBMITTED`) → `VERIFIED` → `SHORTLISTED` / `WAITLISTED` / `REJECTED` → `ADMITTED` (locks the record and, per ADR-003, triggers SIS to persist the `Student` stub — the mechanics of that step are Phase 6's scope, not this document's).

---

## Entity: `SeatAllocation` (ENT-ADM-002, table `seat_allocations`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| class_id | BIGINT UNSIGNED | N | – | FK → Academic's `classes` (cross-module, plain FK) |
| academic_session_id | BIGINT UNSIGNED | N | – | FK → Academic's `academic_sessions` (cross-module, plain FK) |
| total_capacity | INT | N | – | Positive integer; BR-ADM-001 |
| rte_quota_capacity | INT | N | – | ≤ 25% of `total_capacity`; BR-ADM-003 |
| seats_filled | INT | N | 0 | ≤ `total_capacity`; BR-ADM-001 |
| rte_seats_filled | INT | N | 0 | ≤ `rte_quota_capacity`; BR-ADM-003 |

Unique constraint: `(class_id, academic_session_id)`. Relationships: one-to-one with the `(Class, AcademicSession)` pair; one-to-many with `Application` via `class_applied_id` (an indirect relationship — `Application` references `Class` directly, not `SeatAllocation`; the Service layer resolves the applicable `SeatAllocation` row by looking up `(class_applied_id, current academic session)`).

`seats_filled`/`rte_seats_filled` are counters maintained by the Service layer as applications move through `ADMITTED` — not something the database increments automatically; a concurrent-update race on these two columns is exactly the kind of safety-critical capacity contest the Company Development Standard's concurrency rule (pessimistic locking for documented safety-critical cases, optimistic otherwise) is meant to cover. Which locking strategy this Service uses is a Phase 4 decision, not decided here.

### `SeatAllocation` Lifecycle

Created at session setup → updated on each confirmed admission → closed at session end (mirrors `AcademicSession`'s own `CLOSED` transition, per `docs/design/academic/Phase-1`).

## Out of scope

- `Document` entity (Administration module, not designed here).
- The FR-02 Confirm Enrollment orchestration itself, including the SIS call and the transaction boundary — covered by Phase 6 and ADR-004.
- Concrete locking mechanism for `SeatAllocation`'s counters — Phase 4, not this document.
