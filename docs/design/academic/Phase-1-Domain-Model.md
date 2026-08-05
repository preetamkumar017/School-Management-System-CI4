---
status: Approved (Original)
last-updated: 2026-08-06
references: ADR-001, Appendix-G Data Dictionary v1.0 (ACAD module entities), Company Development Standard
---

# Phase 1 — Academic Domain Model

## Scope

Per ADR-001: `Academic` (`App\Modules\Academic`) owns five entities plus one
junction table, all classified Master data. No Business Rule is enforced
exclusively by Academic today, but Academic-owned fields are cited by rules
in other modules (e.g. BR-EXM-007 governs `GradingScheme.grade_band_json`,
BR-SIS-005 governs `Section.capacity`) — those rules are enforced where the
transaction happens (Examination, SIS), not inside Academic itself; Academic
only guarantees the master-data row is well-formed.

Field lists below are taken directly from Appendix-G's ACAD module entity
cards — this document adds no field Appendix-G doesn't already specify.

---

## Entity: `AcademicSession` (ENT-ACAD-001, table `academic_sessions`)

Extends `App\Core\BaseEntity` (surrogate `academic_session_id`, `version`, common audit columns).

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| session_name | VARCHAR(20) | N | – | Unique; format `YYYY-YY` (e.g. `2026-27`) |
| start_date | DATE | N | – | Must be before `end_date` |
| end_date | DATE | N | – | Must be after `start_date` |
| status | enum (`PLANNED`, `ACTIVE`, `CLOSED`, `ARCHIVED`) | N | PLANNED | BR-SIS-001 |

Unique constraint: `session_name`; and a non-overlapping-date-range constraint on `(start_date, end_date)` — cross-row, so enforced in the Service layer per the Company Development Standard's constraint-to-layer rule (§4.8: a rule requiring comparison across rows is a business rule, not a DB constraint), not as a DB-level check.

No foreign keys. This is the anchor entity nearly every transactional entity in the system references (`Application`, `SeatAllocation`, `AttendanceRecord`, `Exam`, `Invoice`, `PromotionRecord`, etc.) — all via plain FK, cross-module where applicable, never a database-level FK across a module boundary per the cross-module rule.

### Lifecycle

Created ahead of the session (`PLANNED`) → `ACTIVE` → `CLOSED` (closing triggers the school's archival-eligibility policy, Appendix-F NFR-ARC-001) → `ARCHIVED`. Forward-only; no document defines a reversal path from `CLOSED` back to `ACTIVE`, and none is assumed here.

---

## Entity: `Class` (ENT-ACAD-002, table `classes`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| class_name | VARCHAR(20) | N | – | Unique (e.g. `Class 6`) |
| sequence_order | INT | N | – | Unique, sequential — drives Examination's promotion-order logic |

No foreign keys. Relationships: one-to-many with `Section`; referenced (cross-module, plain FK) by Admission's `Application`/`SeatAllocation`, SIS's `Student` (via `Section`), and Fees' `FeeStructure`.

### Lifecycle

Created at implementation time → rarely modified → never hard-deleted; only deactivated if the curriculum changes (soft-delete via the standard `is_deleted` column, not a distinct status field — `Class` has no `status` column of its own per Appendix-G).

---

## Entity: `Section` (ENT-ACAD-003, table `sections`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| class_id | BIGINT UNSIGNED | N | – | FK → `classes`, RESTRICT |
| section_name | VARCHAR(10) | N | – | Unique within `class_id` (e.g. `A`) |
| capacity | INT | N | – | Positive integer; BR-SIS-005 (capacity check on section transfer, enforced by SIS at transfer time — Academic only stores the configured number) |

Unique constraint: `(class_id, section_name)`. Relationships: many-to-one with `Class`; referenced (cross-module, plain FK) by SIS's `Student.section_id` and by `TimetableEntry`.

This is the entity DG-SIS-001 (open, see `docs/design/sis/DG-SIS-001.md`) needs an answer from: specifically, *when in the Admission→SIS stub-creation flow a `Section` gets assigned to a new `Student`* is a question about SIS/Admission's own orchestration, not about `Section` itself — Academic's role is limited to exposing a Service method that validates a given `section_id` exists and reports its current occupancy; Academic does not decide when that method gets called during stub creation. This document does not resolve DG-SIS-001.

### Lifecycle

Created at implementation time → modified on capacity changes → rarely deactivated.

---

## Entity: `Subject` (ENT-ACAD-004, table `subjects`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| subject_name | VARCHAR(50) | N | – | Non-empty |
| subject_code | VARCHAR(10) | N | – | Unique (e.g. `MATH`) |

No direct foreign keys — mapped to `Class` only through the `ClassSubjectMap` junction. Relationships: many-to-many with `Class` (via `ClassSubjectMap`); referenced (cross-module) by `TimetableEntry` and Examination's `MarksRecord`.

### Lifecycle

Created at curriculum setup → rarely modified → deactivated if curriculum changes.

---

## Entity: `GradingScheme` (ENT-ACAD-005, table `grading_schemes`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| scheme_name | VARCHAR(50) | N | – | Unique (e.g. `CBSE Standard Grading`) |
| board_type | enum (`CBSE`, `ICSE`, `STATE_BOARD`) | N | – | BR-EXM-007 |
| grade_band_json | JSON | N | – | Non-overlapping, ascending grade-band ranges (e.g. `{"A1":"91-100"}`); BR-EXM-007. Cross-field structural validity within the JSON blob is a Service-layer check, not a DB constraint (Company Development Standard §4.8). |

No foreign keys. Relationships: one-to-many with Examination's `Exam`.

### Lifecycle

Created per board affiliation → modified in place while unreferenced by any closed `Exam` → **becomes effectively immutable once a closed `Exam` references it** (Phase 4's decision) — a "new version" is a new `GradingScheme` row with a new `scheme_name`, not a mutation of the original. This guarantees a previously closed exam's report cards remain reproducible without a dedicated version-history mechanism.

---

## Junction: `ClassSubjectMap` (table `class_subject_map`)

Not individually specified as its own entity card in Appendix-G (only referenced from `Subject`'s Relationships line: "Many-to-Many with Class via `class_subject_map` junction"). Modeled here, consistent with how `StudentGuardianLink` was modeled in the SIS domain design, as a plain junction with a composite key and no audit-column baseline:

| Field | Type | Null | Default | Constraint |
|---|---|---|---|---|
| class_id | BIGINT UNSIGNED | N | – | Composite PK part 1, FK → `classes`, RESTRICT |
| subject_id | BIGINT UNSIGNED | N | – | Composite PK part 2, FK → `subjects`, RESTRICT |

No additional fields are evidenced anywhere in the approved documentation (no "is elective," no "assigned teacher" column is specified) — if a future requirement needs either, that is a new field addition to this table, not implied by anything designed here.

## Out of scope

- Timetable's own domain (`TimetableEntry` is a distinct module, Appendix-G "Timetable & Scheduling," not designed here).
- Examination's consumption of `GradingScheme`/`Subject` (Examination module, not yet designed).
- The concrete `updateGradingScheme` implementation (decided in Phase 4; this document covers only the entity-level lifecycle consequence).
