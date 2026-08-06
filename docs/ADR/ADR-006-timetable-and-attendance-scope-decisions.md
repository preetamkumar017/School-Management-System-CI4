---
status: Accepted
date: 2026-08-06
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-06 — see Context, same delegation ADR-004/ADR-005 record)
relates-to: Appendix-C v1.1 (BR-TT-001–006, BR-ATT-001–007); Appendix-E (FR-10–16); ADR-005 (BR-EXM-005's Attendance seam, now closed)
---

# ADR-006: Timetable and Attendance scope — a real cross-module dependency, and undesigned-module stubs

## Context

The roadmap's Stage 6b ordering assumed Attendance and Timetable had "no
inter-dependency between the two." Appendix-G's `AttendanceRecord` entity
card contradicts that: `timetable_entry_id → TimetableEntry` is a listed
Foreign Key, and `TimetableEntry` is `AttendanceRecord`'s named Parent
Entity. Attendance genuinely depends on Timetable, not the reverse — this
ADR builds Timetable first, Attendance second, in the same pass, rather
than two independent modules.

A second, deeper dependency surfaces once inside Timetable:
`TimetableEntry.employee_id → Employee`, and `Employee` belongs to HR &
Payroll (`ENT-HR-001`), a module that is not designed, not built, and not
next in any reasonable sequencing (Fees was the roadmap's next suggestion
after Attendance/Timetable). Building HR & Payroll's `Employee` entity
just to satisfy one FK on `TimetableEntry` would silently balloon this
stage's scope into a second, unplanned module. Per the same delegation
ADR-004/ADR-005 recorded, that expansion is not made unilaterally — this
ADR instead stubs the dependency the same way ADR-005 stubbed Attendance/
Fees for Examination, and states that decision explicitly rather than
building around it quietly.

Two entities this pass also touches are deferred entirely, for reasons
stated per-item below: `StaffAttendanceRecord` (ENT-ATT-002, needs
`Employee` for its very reason to exist — payroll reconciliation — not
just one FK) and any `Substitution` workflow (FR-16, no such entity
exists anywhere in Appendix-G; it is described only in prose, and itself
depends on staff-absence data this pass doesn't have).

## Decision

### 1. `TimetableEntry.employee_id` is stored, not validated

`employee_id` is a plain `BIGINT UNSIGNED` column with no existence check
against anything — there is no `Employee` table to check against. This is
the identical shape to Admission's `Application.class_applied_id` before
Academic existed, except here the dependency (HR & Payroll) is not
planned for the immediate next stage. A future HR & Payroll module design
must account for this seam: once `Employee` exists, `TimetableController`'s
create/update validation gains a real existence check, exactly as
`ExamController` gained one for `student_id` once SIS existed.

### 2. `StaffAttendanceRecord` is deferred entirely, not stubbed

Unlike `employee_id` on `TimetableEntry` (one FK on an otherwise
self-contained entity), `StaffAttendanceRecord`'s whole purpose — BR-ATT-005
reconciliation against Leave, feeding BR-HR-001 payroll — has no meaning
without `Employee` and HR & Payroll's `Leave`/`PayrollRun` entities. Stubbing
it would produce an entity with no real behavior to test or use. It is not
implemented this pass; `docs/design/School-ERP-Module-Architecture.md`'s
Attendance row is updated to reflect only `AttendanceRecord` as delivered,
`StaffAttendanceRecord` still Not yet designed, owned by whichever pass
designs HR & Payroll.

### 3. BR-TT-004 (Substitution Eligibility) and FR-16 are out of scope

No `Substitution` entity exists in Appendix-G's catalogue — FR-16 describes
a workflow, not a modeled entity, and its own precondition is "a teacher
absence is recorded (FR-12)," which needs `StaffAttendanceRecord` (§2,
deferred). Not implemented; no placeholder field or table added.

### 4. BR-TT-003 (Lab Capacity Ceiling) is not enforced

The rule needs a configured room/lab capacity number. Appendix-G's
`TimetableEntry.room_id` is a bare `VARCHAR(20)` identifier — no `Room`
entity with a `capacity` field exists anywhere in the approved catalogue
(Library/Transport/Facilities entities are all separately unmodeled).
There is nothing to compare a section's enrolled count against. Not
enforced; a future `Room`/facilities entity would close this gap, the
same way a future `Configuration` entity closes Examination's board-
affiliation and anomaly-threshold gaps (ADR-005 §4, §6).

### 5. BR-TT-001/002 (no double-booking) — implemented for real

Both are fully self-contained against `TimetableEntry`'s own data — no
undesigned-module dependency. `(section_id, day_of_week, period_no)` and
`(employee_id, day_of_week, period_no)` are DB-level unique constraints,
exactly as Appendix-G's Index Recommendations specify. Room
double-booking `(room_id, day_of_week, period_no)` is a Service-layer
check, not a DB unique constraint, since `room_id` is optional and not
named in Appendix-G's own Index Recommendations line the way the other
two composites are — the DB layer honors exactly what's documented,
the Service layer covers the rest.

### 6. BR-TT-005 (Publication Lock) — versioning implemented, notification stubbed

`status` (`DRAFT`/`PUBLISHED`) and `version_no` (increment-on-revision) are
implemented exactly as specified. The "notification dispatched via the
Communication module" post-condition is not — Communication is
undesigned, same reasoning as Examination's report-card-PDF exclusion
(ADR-005 §9). A revision still succeeds; it simply doesn't notify anyone,
which is a named gap, not a silent one.

### 7. BR-TT-006 (Weekly Teaching Load Ceiling) — implemented with a decided default

`TimetableEntry` rows are a recurring weekly template (`day_of_week` +
`period_no`, no date), so "weekly load" is simply the count of an
employee's `TimetableEntry` rows across all days. The ceiling itself is
"Client/Product Decision Required" per Appendix-C — decided here as **30
periods/week**, a documented, tunable default (same treatment as
Examination's 30-percentage-point anomaly threshold, ADR-005 §6),
pending a future `Configuration` entity.

### 8. BR-ATT-001/002/003 — implemented; correction workflow reuses Examination's pattern

BR-ATT-001 (single state per period) is the entity's own unique constraint.
BR-ATT-002/003 (lock after approval; late correction needs Academic Head
approval) reuse the exact shape ADR-005 established for BR-EXM-003: no
`ApprovalRequest` entity exists, so a correction is a single logged action
requiring `override_reason`, logged via `AuditLog::ACTION_OVERRIDE` — not
a multi-step approval queue. The "configured edit window" (Appendix-C:
"Client/Product Decision Required") is decided as **same calendar day as
`attendance_date`**: within that day, a Teacher may correct directly; past
it, `override_reason` is required. This mirrors Examination's
`ClosedSessionGuard` shape (a boundary condition gating a logged override)
scoped to a day instead of an academic session.

### 9. BR-ATT-004 (Absence Notification) is not implemented

Dispatch depends on the Communication module (undesigned) and FR-37
(also undesigned, referenced but out of any current stage). Not
implemented — no notification-log entity, no dispatch call. A future
Communication module design closes this gap.

### 10. BR-ATT-005 (Staff reconciliation) and BR-ATT-007 (Biometric consistency) are not implemented

§2 already excludes `StaffAttendanceRecord` (BR-ATT-005's subject
entity). BR-ATT-007 needs a "biometric capture active for this class"
configuration flag that exists nowhere in the approved schema (no such
column on `TimetableEntry` or any other entity) — same reasoning as
BR-TT-003's missing capacity number. Not implemented.

### 11. BR-ATT-006 (Exam Eligibility) — implemented for real, closing ADR-005 §2's seam

Unlike the Attendance-side rules above, this one is fully computable from
data this pass creates: `AttendanceService::calculateAttendancePercentage`
aggregates a student's `AttendanceRecord`s over a date range;
`isExamEligibilityAtRisk(int $studentId): bool` compares that percentage
against a decided threshold — **75%**, a standard, commonly used minimum
in Indian school policy and a documented default pending a future
`Configuration` entity (same treatment as items 7 and BR-EXM-006).
Examination's `MarksRecordService::createMarksRecord` — which ADR-005 §2
left as an always-eligible stub — is updated to call this method for
real. The dependency direction is Examination → Attendance (Examination
already depends on Academic/SIS; Attendance depends on Academic/SIS/
Timetable only, never on Examination), so this is a new one-way edge,
not a cycle. If the flag is set, `createMarksRecord` now requires an
`override_reason` (logged via `AuditLog::ACTION_OVERRIDE`) exactly as
BR-EXM-005's text describes — "cannot be marked as having appeared for
an exam without Academic Head override."

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s Timetable and
  Attendance rows are updated: Timetable → Designed (`TimetableEntry`
  only); Attendance → Designed (`AttendanceRecord` only,
  `StaffAttendanceRecord` still Not yet designed).
- `docs/design/timetable/` and `docs/design/attendance/` (this ADR's
  Phase docs) proceed on the basis of every decision above; none are
  re-derived there.
- `App\Modules\Examination\Services\MarksRecordService::createMarksRecord`
  changes in this same pass to call
  `AttendanceService::isExamEligibilityAtRisk` instead of doing nothing
  (ADR-005 §2's stub) — a small, targeted change to already-shipped
  Stage 6a code, not a new module boundary violation (Examination already
  depended on cross-module Services; this adds one more, in the allowed
  direction).
- A future HR & Payroll module design must account for: `TimetableEntry.
  employee_id` gaining a real existence check (§1); `StaffAttendanceRecord`
  being designed and implemented there or in a joint pass with Attendance
  (§2); BR-TT-004/FR-16 Substitution becoming implementable once staff
  absence data exists (§3).
- A future `Configuration` module design must account for: the lab-capacity
  ceiling (§4, plus a `Room` entity), the weekly load ceiling default (§7),
  the attendance edit-window boundary (§8), and the exam-eligibility
  threshold (§11) — four candidate settings, joining Examination's two
  from ADR-005.
- A future Communication module design must account for: timetable
  revision notifications (§6) and absence notifications (§9).
