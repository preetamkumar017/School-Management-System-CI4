---
status: Approved
last-updated: 2026-08-07
references: Appendix-C v1.1 BR-TT-004, Appendix-E v1.0 FR-16, ADR-013, ADR-008 §11
---

# Phase 4 — Timetable Substitution (BR-TT-004 / FR-16)

## Scope

Follow-up pass, unblocked by ADR-008 §11 (`StaffAttendanceRecord` now
exists) and decided by ADR-013. Adds two entities to `Timetable`:
`SubjectTeacherEligibility` (ENT-TT-002) and `Substitution` (ENT-TT-003).

## Entity: `SubjectTeacherEligibility` (ENT-TT-002, table `subject_teacher_eligibilities`)

Extends `App\Core\BaseEntity`. Net-new per ADR-013 §2 — no entity in
Appendix-G's Data Dictionary models BR-TT-004's "configured
subject-teacher eligibility mapping."

| Field | Type | Null | Constraint |
|---|---|---|---|
| employee_id | BIGINT UNSIGNED | N | Cross-module ref → HR & Payroll `Employee`, validated via `EmployeeService` |
| subject_id | BIGINT UNSIGNED | N | Cross-module ref → Academic `Subject`, validated via `SubjectService` |

Unique constraint: `(employee_id, subject_id)`. Create + read only — no
update/delete endpoint (ADR-013 §2, ADR-009 §13 precedent).

## Entity: `Substitution` (ENT-TT-003, table `substitutions`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| timetable_entry_id | BIGINT UNSIGNED | N | – | FK → `timetable_entries` (same-module, RESTRICT) |
| absent_employee_id | BIGINT UNSIGNED | N | – | Copied from the entry's `employee_id` at creation time |
| substitute_employee_id | BIGINT UNSIGNED | Y | NULL | NULL when `status = UNSUPERVISED` |
| substitution_date | DATE | N | – | The one date this substitution applies to — never mutates `timetable_entries` (ADR-013 §3) |
| status | enum (`ASSIGNED`, `UNSUPERVISED`) | N | – | ADR-013 §1 |

Unique constraint: `(timetable_entry_id, substitution_date)` — at most
one substitution per entry per date.

### `Substitution` Lifecycle

`TimetableEntry` (PUBLISHED) + `StaffAttendanceRecord` shows the
assigned teacher `Unauthorized`/`On Leave` on a date → Academic Head
calls `GET .../eligible-substitutes` to review candidates → `POST
substitutions` either with an explicit `substitute_employee_id`
(validated: eligible for the subject, not already booked that
day/period) or without one (system auto-picks the first eligible/
available candidate) → `ASSIGNED` if a substitute was found/supplied,
`UNSUPERVISED` otherwise (ADR-013 §1) → one `NotificationLog` row
logged against the real employee on the other side of the transaction
(substitute if `ASSIGNED`, absent teacher if `UNSUPERVISED` — ADR-013
§4). No further transitions modeled this pass — manual
resolution of an `UNSUPERVISED` row is an operational, not a system,
step.

## Service layer

- `SubjectTeacherEligibilityService::createEligibility()`,
  `::listBySubject()`.
- `SubstitutionService::createSubstitution()`, `::getSubstitution()`,
  `::listEligibleSubstitutes()` — the eligible-candidate computation
  (subject match minus already-double-booked employees) is shared
  between the review endpoint and the auto-pick path inside
  `createSubstitution()`.
- `App\Modules\Attendance\Services\StaffAttendanceService::wasAbsentOn()`
  — new method, the one-way Timetable → Attendance call gating
  `createSubstitution()` (ADR-013 §5).

## Routes (base `/api/v1/timetable`)

| Method | Path | Controller::method |
|---|---|---|
| POST | `subject-teacher-eligibilities` | `SubjectTeacherEligibilityController::create` |
| GET | `subject-teacher-eligibilities?subject_id=` | `SubjectTeacherEligibilityController::index` |
| POST | `substitutions` | `SubstitutionController::create` |
| GET | `substitutions/{id}` | `SubstitutionController::show` |
| GET | `entries/{id}/eligible-substitutes` | `SubstitutionController::eligibleSubstitutes` |

## Out of scope

- Live SMS/email dispatch — log-only via `NotificationLogService`
  (ADR-013 §4, ADR-010 §3 precedent).
- Per-guardian notification fan-out — `NotificationLogService` validates
  `recipient_ref_id` against a real entity, and Timetable has no
  guardian-enumeration dependency to produce one; logged against the
  real Employee on the other side of the transaction instead, pending a
  future SIS-integration pass (ADR-013 §4).
- Manual resolution workflow for `UNSUPERVISED` rows beyond the
  persisted record itself — no further status transition modeled.
- BR-TT-003 lab capacity ceiling — remains out of scope per ADR-006 §4,
  unrelated to this pass.
