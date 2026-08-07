---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-012 record)
relates-to: Appendix-C BR-TT-004 (Substitution Eligibility); Appendix-E FR-16 (Substitution Management); ADR-006 §3 (original deferral); ADR-008 §11 (unblocking note); docs/design/timetable/Phase-1-Domain-Model.md (out-of-scope note)
---

# ADR-013: Timetable Substitution — scope decisions

## Context

ADR-006 §3 deferred BR-TT-004/FR-16 (Substitution) when Timetable first
shipped: no `Substitution` entity existed in Appendix-G, and the feature
needs `StaffAttendanceRecord` to know whether a teacher is actually
absent on a given date — a table that didn't exist yet. ADR-008 §11
built `StaffAttendanceRecord` (Attendance module, `Module: ATT` per
Appendix-G) and explicitly flagged BR-TT-004/FR-16 as "now unblocked...
flagged for a future Timetable follow-up pass." This ADR is that pass.

Two requirements govern this feature:

- **BR-TT-004 (Substitution Eligibility)**: a substitute assigned to a
  vacant period must be subject-eligible per a "configured
  subject-teacher eligibility mapping." No such mapping entity exists
  anywhere in Appendix-G's Data Dictionary — genuinely net-new.
- **FR-16 (Substitution Management)**: on teacher absence, Academic Head
  reviews eligible substitutes and assigns one; students/parents are
  notified (references FR-37). Alt flow: if no eligible substitute
  exists, the period is marked "Unsupervised" pending manual resolution.
  The substitution applies "for that date only." Appendix-E explicitly
  flags "no fallback policy defined if no eligible substitute exists" as
  a Client/Product Decision Required.

Per the standing delegation established in ADR-004 through ADR-012, this
ADR resolves the open items below rather than leaving them pending.

## Decision

### 1. Fallback policy when no eligible substitute exists: create an UNSUPERVISED record, never reject the request

FR-16's own alt flow already names the answer — "period is marked
Unsupervised pending manual resolution" — so the fallback is not
actually undefined in Appendix-E's prose, only unconfirmed as a
mechanical `Substitution.status` value. `createSubstitution()` always
succeeds when the absence check and (if a substitute is explicitly
supplied) the eligibility/availability checks pass; if no substitute is
available — whether omitted by the caller or none exists in the
eligibility mapping without a scheduling conflict — the row is created
with `status = UNSUPERVISED` and `substitute_employee_id = NULL` rather
than throwing. This gives the Academic Head a persisted, queryable
record of every vacant period instead of a rejected API call with no
trace. A substitute that is explicitly supplied but genuinely
ineligible/unavailable is still a hard reject
(`SUBSTITUTE_NOT_ELIGIBLE`/`SUBSTITUTE_NOT_AVAILABLE`) — that is a
caller error, not the "no fallback policy" case Appendix-E is asking
about.

### 2. Subject-teacher eligibility mapping: a real persisted table, minimal CRUD

Appendix-C's own language — "configured... mapping" — points at real,
persisted reference data, not a computed rule. `SubjectTeacherEligibility`
(`employee_id`, `subject_id`, unique pair) is added to Timetable. Only a
`POST` (create one pair, admin-managed) and `GET ?subject_id=` (list
employees eligible for a subject) are exposed — no update/delete, since
nothing in BR-TT-004 needs to mutate a pair rather than add/remove one,
and no business rule asks for an editable value on the row itself. This
matches ADR-009 §13's precedent against building CRUD or aggregate
methods beyond what the feature in front of us actually needs.

### 3. Date-scoped, not version-bumped — confirmed separate from BR-TT-005

`Substitution` is its own table (`substitutions`), one row per
`timetable_entry_id` + `substitution_date` (unique pair). Creating one
never touches `timetable_entries` — no `UPDATE`, no `version_no`
increment. `TimetableEntryService::reviseEntry()` (BR-TT-005) remains
the only path that mutates a `TimetableEntry` row, and it still means
"the recurring schedule changed going forward." A `Substitution` means
"this one date's occurrence of an otherwise-unchanged recurring entry
had a different teacher, or none." Keeping them as separate tables
makes this distinction structural, not just documented.

### 4. Notification: log-only, real recipient — no live dispatch, no fabricated Guardian id

Reuses `NotificationLogService::create()` exactly as
`TimetableEntryService::reviseEntry()` already does for BR-TT-005 — no
live SMS/email gateway, per ADR-010 §3's standing decision.
`NotificationLogService::create()` calls `validateRecipient()`, which
resolves `recipient_ref_id` against a real entity of `recipient_type`
(`GuardianService::getGuardian()` for `Guardian`) — confirmed by running
the new tests, which failed with `Guardian not found` against a
first-draft version of this ADR that logged `recipient_type = Guardian,
recipient_ref_id = section_id`; `section_id` is not a `Guardian` id.
FR-16 says "students/parents get notified," but Timetable has no
existing dependency on enumerating a section's students and their
guardians (that lookup lives in SIS, and Timetable has never had a
reason to reach into it before this feature) — building that fan-out
here would be a speculative cross-module addition for a step FR-16
does not itself specify the mechanics of, and fabricating a
`recipient_ref_id` that doesn't resolve to a real `Guardian` would
either fail this validation or silently corrupt `NotificationLog`.
`createSubstitution()` instead logs one `NotificationLog` row
(`recipient_type = Employee`) against the real employee on the other
side of the transaction — the substitute's `employee_id` when
`ASSIGNED`, the originally-absent teacher's `employee_id` when
`UNSUPERVISED` — both already validated via `EmployeeService`
elsewhere in the same request. A future SIS-integrated pass that wants
true per-guardian substitution alerts should build the guardian
enumeration this ADR deliberately does not, rather than reuse this
row's shape.

### 5. Absence gate: `StaffAttendanceService::wasAbsentOn()`, one-way Timetable → Attendance

`createSubstitution()` calls this new `StaffAttendanceService` method
before anything else — true only for a `StaffAttendanceRecord` that date
in state `Unauthorized` or `On Leave`; false for `Present` or no record
at all (`TEACHER_NOT_ABSENT` otherwise). This is a one-way dependency —
Timetable calling into Attendance's Service, the same shape as
Timetable already calling `EmployeeService`/`SectionService`/
`SubjectService`. Attendance gains no new dependency on Timetable, so no
cycle is introduced.

## Consequences

- `docs/design/timetable/Phase-1-Domain-Model.md`'s substitution
  out-of-scope note is superseded by
  `docs/design/timetable/Phase-4-Substitution-Design.md`.
- Two new tables: `subject_teacher_eligibilities`, `substitutions`.
  `substitutions.timetable_entry_id` is a same-module FK (RESTRICT);
  `absent_employee_id`/`substitute_employee_id` are cross-module
  references to HR & Payroll's Employee, stored without a DB-level FK —
  same convention `timetable_entries.employee_id` already uses.
- `App\Modules\Attendance\Services\StaffAttendanceService` gains
  `wasAbsentOn()` — a small, targeted addition to already-shipped code,
  the same shape ADR-011 §4 used when wiring `ConfigurationService` into
  six existing Services.
- A future SIS-integration pass that wants true per-guardian
  substitution alerts should re-check §4 first — the employee-recipient
  log row this ADR ships is deliberately not that, and upgrading it
  means adding a guardian-enumeration dependency Timetable does not
  have today.
