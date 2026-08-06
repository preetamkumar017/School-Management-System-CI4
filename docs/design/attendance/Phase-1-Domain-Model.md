---
status: Approved (Original)
last-updated: 2026-08-06
references: Appendix-G Data Dictionary v1.0 (ENT-ATT-001), Appendix-C v1.1 (BR-ATT-001–007), ADR-006
---

# Phase 1 — Attendance Domain Model

## Scope

Per ADR-006: `Attendance` (`App\Modules\Attendance`) owns one entity this
pass, `AttendanceRecord` (Transaction data). `StaffAttendanceRecord`
(ENT-ATT-002) is deferred entirely (ADR-006 §2) — it needs HR & Payroll's
`Employee`/`Leave` to have any real behavior. BR-ATT-004/005/007 are out
of scope (ADR-006 §9, §10); BR-ATT-001/002/003/006 are implemented.

## Entity: `AttendanceRecord` (ENT-ATT-001, table `attendance_records`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| student_id | BIGINT UNSIGNED | N | – | FK → SIS's `students` (cross-module, plain FK, validated via `StudentService`) |
| timetable_entry_id | BIGINT UNSIGNED | N | – | FK → Timetable's `timetable_entries` (cross-module, plain FK, validated via `TimetableEntryService`) |
| attendance_date | DATE | N | – | |
| state | enum (`PRESENT`, `ABSENT`, `LATE`) | N | – | BR-ATT-001 |
| marked_by | BIGINT UNSIGNED | N | – | FK → Administration's `users` (cross-module, plain FK — not validated; the authenticated caller's own user ID, taken from `RequestContext::userId()`, same as every other module's `created_by` stamping, never client-supplied) |
| is_locked | BOOLEAN | N | FALSE | BR-ATT-002 |

Unique constraint: `(student_id, timetable_entry_id, attendance_date)` —
BR-ATT-001's enforcement mechanism.

### `AttendanceRecord` Lifecycle

Created (marked) → Locked (`is_locked = true`, BR-ATT-002) → Corrected (a
single logged action requiring `override_reason` once past the same-day
edit window, ADR-006 §8 — not a persisted intermediate "pending approval"
status, same reasoning Examination's re-evaluation gave for not modeling
one) → Archived at academic-session close.

## Out of scope

- `StaffAttendanceRecord` (ADR-006 §2).
- BR-ATT-004 absence notification dispatch (ADR-006 §9).
- BR-ATT-005 staff-leave reconciliation (ADR-006 §10 — no subject entity).
- BR-ATT-007 biometric/manual consistency (ADR-006 §10 — no capture-method
  configuration exists anywhere in the approved schema).
