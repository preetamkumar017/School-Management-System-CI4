---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004/ADR-005/ADR-006 record)
relates-to: Appendix-C v1.1 (BR-HR-001–007); Appendix-E (FR-33–36); ADR-006 (§1 TimetableEntry.employee_id stub, §2 StaffAttendanceRecord deferral — both closed by this ADR)
---

# ADR-008: HR & Payroll module scope — closing two deferred seams, and undesigned-workflow gaps

## Context

ADR-006 deferred two items pending an HR & Payroll design pass: `TimetableEntry.
employee_id` was stored without an existence check (§1), and `StaffAttendanceRecord`
(`ENT-ATT-002`) was not built at all, since its entire purpose — BR-ATT-005
reconciliation against Leave, feeding BR-HR-001 payroll — has no meaning without
`Employee`. Both are closed by this ADR.

`Employee`'s own FK graph is otherwise clean: `department_id → Department`,
`designation_id → Designation` (both new HR master data), and no dependency on
Academic, SIS, Examination, Timetable, or Fees. The only pre-existing module it
touches is Administration's `User` (`LeaveRequest.approver_id → User`, and BR-HR-002's
account-deactivation call), which is foundational and already built (Stage 1).

`StaffAttendanceRecord` reopens the cross-module-cycle question ADR-005 (Academic↔
Examination) and ADR-003 (Admission↔SIS) already resolved once each. It is listed
under `Module: ATT` in Appendix-G (owner *department* is HR, but the entity itself
is Attendance's), and it needs `employee_id → Employee` validated (Attendance → HR,
one-way — the same direction ADR-006 §1 already established for `TimetableEntry`).
Symmetrically, BR-HR-001 needs Payroll to know when staff attendance for a period is
closed (HR needs data Attendance owns). A live HR → Attendance call to check that at
payroll-run time would complete a cycle with the Attendance → HR validation call.
This ADR resolves it the same way ADR-005 §10 resolved Academic↔Examination: one
real query direction (Attendance → HR, for `employee_id` existence), and one
one-way **push** in the other direction (Attendance → HR, again — not HR reading
Attendance) that writes an additive closure record HR then reads locally. Both
cross-module edges point the same way; there is no cycle.

Several other BR-HR rules reference concepts with no corresponding entity anywhere
in Appendix-G's approved catalogue (leave balance figures, statutory deduction
slabs, a "settlement" record) — these are resolved per-item below, following the
same pattern used throughout ADR-005/006/007: a documented default where a concrete
number is missing, or an explicit deferral where no entity exists at all, never an
invented, unapproved entity.

## Decision

### 1. `Employee`, `Department`, `Designation` — implemented as specified

All three map directly onto their Appendix-G attribute catalogues with no gaps.
`Department`/`Designation` are simple master data (`*_name` unique). `Employee`
carries `salary_structure_json` and `status` (`Active`/`Exited`) exactly as
specified; `exit_date` gates BR-HR-002.

### 2. `TimetableEntry.employee_id` gains a real existence check, closing ADR-006 §1

`App\Modules\Timetable\Services\TimetableEntryService` now calls HR & Payroll's
`EmployeeService::getEmployee(int $id)` on create/update, exactly as
`ExamController` gained a real `student_id` check once SIS existed. Timetable →
HR & Payroll is a new one-way edge; Timetable already depends on Academic/SIS,
never the reverse, so this does not create a cycle.

### 3. `StaffAttendanceRecord` is implemented now, inside the Attendance module

Built as `App\Modules\Attendance\Entities\StaffAttendanceRecord` /
`StaffAttendanceRecordService`, per Appendix-G's `Module: ATT` designation — a
sibling to `AttendanceRecord`, not a new HR sub-entity — with the fields specified
(`employee_id`, `attendance_date`, `state` [Present/On Leave/Unauthorized],
`is_reconciled`), unique on `(employee_id, attendance_date)`. `employee_id` is
validated against HR & Payroll's `EmployeeService::getEmployee()` (§2's direction,
reused). BR-ATT-005 reconciliation (cross-checking against `LeaveRequest`) is
implemented as a Service method that marks `state = 'On Leave'` and
`is_reconciled = true` for any date range covered by an `Approved` `LeaveRequest`
for that employee — a read-only HR & Payroll → nothing call is not needed here,
since Attendance already owns both `StaffAttendanceRecord` and can query HR & Payroll
directly (Attendance → HR & Payroll, same allowed direction as §2/§3's existence
check).

### 4. BR-HR-001 (Payroll Requires Attendance Closure) — implemented via a one-way push, not a live cross-module read

A new HR & Payroll–owned table, `attendance_closures` (`employee_id`, `pay_period`
[`YYYY-MM`], `closed_at`, `closed_by`), unique on `(employee_id, pay_period)`, is
written by a new `App\Modules\Attendance\Services\StaffAttendanceService::
closePeriod(int $employeeId, string $payPeriod)` method that calls HR & Payroll's
`EmployeeService::recordAttendanceClosure()` once every `StaffAttendanceRecord` for
that employee/period is reconciled. This mirrors ADR-005 §10's `locked_by_closed_exam`
additive-column pattern exactly: Attendance pushes a fact into HR & Payroll's own
storage; HR & Payroll never calls back into Attendance. `PayrollRunService::create()`
checks for a matching `attendance_closures` row before allowing a `Draft` run for
that employee/period — if absent, creation is blocked with a `BusinessRuleException`
naming the unreconciled employee, matching Appendix-C's specified exception handling.

### 5. BR-HR-002 (Access Revocation on Exit) — implemented synchronously, no SLA config

`EmployeeService::update()`, on transitioning `exit_date` from null to a value,
looks up the linked `User` via Administration's existing
`UserModel::findByOwner('EMPLOYEE', $employeeId)` and calls
`UserService::changeStatus()` to `STATUS_DEACTIVATED`, inside the same transaction
as the `Employee` update. Appendix-C's "if automatic deactivation fails, raise an
exception to IT Admin within a defined SLA (Client/Product Decision Required)" is
resolved by making failure impossible to silently succeed around: if the
deactivation call throws, the `Employee` update is rolled back entirely (fail
closed), rather than adding an async notification/SLA mechanism that doesn't exist
anywhere else in this codebase. HR & Payroll → Administration is the allowed
direction (Administration is foundational, already depended on by every module).

### 6. BR-HR-003 (No Duplicate Payroll Run) and BR-HR-007 (Payslip Immutability) — implemented via `PayrollRun.status`

`PayrollRun`'s own `(employee_id, pay_period)` unique constraint plus its
`Draft → Approved → Processed` status lifecycle (Appendix-G's own Lifecycle line)
covers both: a second `create()` for the same employee/period is blocked by the
unique constraint (BR-HR-003), and once `status = 'Processed'`, `update()` rejects
any field change (BR-HR-007), the same status-lock shape as Fees' `Invoice.is_locked`
and Examination's closed-session immutability. "Reversal"/"supplementary adjustment
record" workflows (Appendix-C's stated escape hatches) are not implemented — no
`Adjustment` or reversal entity exists in Appendix-G; a `Processed` run is simply
immutable, full stop, until a future design pass adds one.

### 7. BR-HR-004 (Leave Balance Validation) — implemented with decided annual allocations, no persisted balance entity

No `LeaveBalance` entity or balance field exists anywhere in Appendix-G's
`LeaveRequest` catalogue. Balances are computed on the fly, the same way
Attendance's percentage (ADR-006 §11) is computed rather than stored: fixed annual
allocations per leave type, decided here as documented, tunable defaults —
**CL: 12/year, SL: 10/year, EL: 15/year**, resetting on the calendar year of
`start_date` (matching FR-35's own stated edge case) — minus the sum of days
across that employee's `Approved` `LeaveRequest`s of the same type in the same
year. `LeaveRequestService::approve()` blocks approval if the projected balance
would go negative, unless an `override_reason` is supplied — Appendix-C's "override
authority is Client/Product Decision Required" is resolved as **HR role, logged**,
reusing the established `override_reason` / `AuditLog::ACTION_OVERRIDE` pattern
(same shape as Examination's re-evaluation and Fees' void/refund).

### 8. BR-HR-005 (Statutory Deduction Mandatory Application) — `deductions_json` is caller-supplied, not auto-calculated

PF/ESI/Professional Tax slabs are explicitly "Client/Product Decision Required" per
Appendix-C, with no `Configuration` entity anywhere to hold them. Matching the
precedent set for Fees' GST/waiver-list gaps (ADR-007), `PayrollRunService::create()`
requires `deductions_json` as caller (HR/Finance) input, validated for presence and
non-negative values, rather than auto-calculating from slabs the system has no
approved source for. `net_pay` is still computed as `gross_pay - sum(deductions_json)`.
A future `Configuration` entity is the documented path to real slab-based
auto-calculation, joining the four candidates already listed in ADR-005/006.

### 9. BR-HR-006 (Full & Final Settlement Precondition) is out of scope

"Full & final settlement" and "exit record" are workflow concepts described only in
Appendix-C prose — no `Settlement` or `ExitRecord` entity, field, or status value
exists anywhere in Appendix-G's `Employee` or any other HR card. `Employee.status`
only distinguishes `Active`/`Exited`; there is no separate "Pending Settlement"
state to gate. Not implemented, matching Fees' `CreditNote` deferral precedent
(ADR-007 §9) — an unapproved entity is not invented to support it. `exit_date`
being set is sufficient to trigger BR-HR-002's access revocation (§5); settlement
confirmation is left to a future HR & Payroll design pass once a `Settlement` entity
is scoped.

### 10. Payslip is data-only — no `Document` child entity, no PDF generation

`PayrollRun.status = 'Processed'` is the payslip-issued signal (Appendix-G lists
`Document (payslip)` as a Child Entity, but no `Document` entity exists in the
approved catalogue). Matching Examination's report-card-PDF exclusion (ADR-005 §9),
FR-36's "Payslip & Statutory Report Generation" is exposed as a read endpoint over
`PayrollRun`'s own fields (gross/net/deductions), not a rendered file. A future
Communication/Document-generation module design closes this gap for payslips,
report cards, and invoices alike.

### 11. BR-TT-004/FR-16 Substitution remains out of scope

ADR-006 §3 deferred Substitution because it needs staff-absence data
(`StaffAttendanceRecord`), which this ADR now builds (§3). It is still not
implemented here: FR-16 describes a workflow, not a modeled entity, and no
`Substitution` entity exists in Appendix-G's catalogue for this pass to build
against. Flagged as now-unblocked for a future Timetable follow-up pass, not
pulled into this one — HR & Payroll's own scope is already substantial.

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s HR & Payroll row is updated to
  Designed, citing this ADR; the Attendance row's `StaffAttendanceRecord` column is
  updated from "Not yet designed" to Designed/delivered.
- `docs/design/hr-payroll/` (this ADR's Phase docs) proceeds on the basis of every
  decision above; none are re-derived there.
- `App\Modules\Timetable\Services\TimetableEntryService` changes in this same pass
  to call `EmployeeService::getEmployee()` for real (§2), a small targeted change to
  already-shipped Stage 6b code, not a new module boundary violation.
- `App\Modules\Attendance` gains `StaffAttendanceRecord` / `StaffAttendanceService`
  in this same pass (§3), alongside HR & Payroll — a joint addition across two
  modules in one stage, the same shape ADR-006 itself used for Timetable+Attendance.
- HR & Payroll gains an additive `attendance_closures` table (§4), following the
  `locked_by_closed_exam` pattern from ADR-005 §10 — the second use of this
  one-way-push shape for resolving a would-be cross-module cycle.
- A future `Configuration` module design must account for: leave-type annual
  allocations and their override authority (§7), and statutory deduction slabs
  (§8) — joining the four candidates already listed in ADR-005/006.
- A future HR & Payroll follow-up design must account for: a `Settlement`/
  `ExitRecord` entity to implement BR-HR-006 (§9), and a reversal/supplementary-
  adjustment entity for BR-HR-003/007's stated correction path (§6).
- A future Communication/Document-generation module design must account for:
  rendered payslips (§10), joining timetable-revision and absence notifications
  already listed in ADR-006's consequences.
- A future Timetable follow-up design must account for: BR-TT-004/FR-16
  Substitution, now unblocked by `StaffAttendanceRecord` existing (§11).
