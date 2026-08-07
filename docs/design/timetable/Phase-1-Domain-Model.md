---
status: Approved (Original)
last-updated: 2026-08-06
references: Appendix-G Data Dictionary v1.0 (ENT-TT-001), Appendix-C v1.1 (BR-TT-001–006), ADR-006
---

# Phase 1 — Timetable Domain Model

## Scope

Per ADR-006: `Timetable` (`App\Modules\Timetable`) owns one entity,
`TimetableEntry` (Reference data, versioned per term). Field list taken
directly from Appendix-G. `employee_id` is stored but not
cross-module-validated (ADR-006 §1 — HR & Payroll's `Employee` doesn't
exist); BR-TT-003 (lab capacity) and BR-TT-004 (substitution) are out of
scope (ADR-006 §3, §4).

## Entity: `TimetableEntry` (ENT-TT-001, table `timetable_entries`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| section_id | BIGINT UNSIGNED | N | – | FK → Academic's `sections` (cross-module, plain FK, validated via `SectionService`) |
| subject_id | BIGINT UNSIGNED | N | – | FK → Academic's `subjects` (cross-module, plain FK, validated via `SubjectService`) |
| employee_id | BIGINT UNSIGNED | N | – | Intended FK → HR & Payroll's `Employee` — stored only, not validated (ADR-006 §1; HR & Payroll doesn't exist) |
| day_of_week | enum (`MONDAY`..`SATURDAY`) | N | – | |
| period_no | INT | N | – | Positive; max periods/day not enforced (no ceiling configured anywhere) |
| room_id | VARCHAR(20) | Y | NULL | No double-booking (BR-TT-002, Service-layer check) |
| version_no | INT | N | 1 | Increments on each revision (BR-TT-005) |
| status | enum (`DRAFT`, `PUBLISHED`) | N | DRAFT | BR-TT-005 |

Unique constraints (DB-level, per Appendix-G's Index Recommendations):
`(section_id, day_of_week, period_no)`, `(employee_id, day_of_week,
period_no)` — together these are BR-TT-001's enforcement mechanism (a
teacher can't have two rows for the same day/period) and half of
BR-TT-002 (a section can't have two rows for the same day/period; the
room half is a Service-layer check per ADR-006 §5, since `room_id` isn't
named in Appendix-G's own composite-index list).

Table name: Appendix-G literally specifies `timetable_entrys` (its own
naming-standard note appears to be a typo against its stated "snake_case,
plural" rule) — this project uses the grammatically correct
`timetable_entries` instead, consistent with every other table in this
codebase (`class_subject_map` aside, which is a junction with its own
naming convention).

### `TimetableEntry` Lifecycle

Created (`DRAFT`) → conflict-checked (BR-TT-001/002, at every create/update,
not only at publish) → Published (`PUBLISHED`, `version_no` starts at 1) →
Revised (any change to a `PUBLISHED` entry creates a new row with
`version_no` incremented, per BR-TT-005 — not an in-place edit) → Archived
at term end (soft-delete only, no distinct status value for it, same
reasoning as Academic's `Class`).

## Out of scope

- `Employee` existence validation (ADR-006 §1).
- BR-TT-003 lab capacity ceiling — no `Room` capacity entity exists
  (ADR-006 §4).
- BR-TT-004 / FR-16 Substitution management — no `Substitution` entity
  exists, depends on staff-absence data not modeled this pass (ADR-006 §3).
  **Superseded 2026-08-07**: `StaffAttendanceRecord` now exists
  (ADR-008 §11) — Substitution is designed and implemented per ADR-013
  and `docs/design/timetable/Phase-4-Substitution-Design.md`.
- Revision notification dispatch (BR-TT-005's Communication-module
  post-condition) — Communication is undesigned (ADR-006 §6).
