---
status: Approved (Original)
last-updated: 2026-08-07
references: Appendix-G Data Dictionary v1.0 (HR module entities, ENT-ATT-002), Appendix-C v1.1 (BR-HR-001–007), ADR-008
---

# Phase 1 — HR & Payroll Domain Model

## Scope

Per ADR-008: `HrPayroll` (`App\Modules\HrPayroll`) owns five entities:
`Employee`, `Department`, `Designation` (all Master except `Employee`,
which is Master-with-lifecycle), `PayrollRun`, `LeaveRequest` (both
Transaction), plus a new additive table `attendance_closures` (ADR-008
§4). `StaffAttendanceRecord` (`ENT-ATT-002`) is built in this same pass
but lives in `App\Modules\Attendance` per Appendix-G's own `Module: ATT`
designation (ADR-008 §3) — documented here because its existence and
shape directly gate `PayrollRun` (BR-HR-001), even though its code and
tests live under Attendance.

## Entity: `Department` (ENT-HR-002, table `departments`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| department_name | VARCHAR(50) | N | – | Unique |

### Lifecycle

Created at setup → rarely modified.

---

## Entity: `Designation` (ENT-HR-003, table `designations`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| designation_name | VARCHAR(50) | N | – | Unique |

### Lifecycle

Created at setup → rarely modified.

---

## Entity: `Employee` (ENT-HR-001, table `employees`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| employee_code | VARCHAR(20) | N | – | Unique |
| full_name | VARCHAR(100) | N | – | Non-empty |
| department_id | BIGINT UNSIGNED | N | – | FK → `departments` (intra-module, real FK) |
| designation_id | BIGINT UNSIGNED | N | – | FK → `designations` (intra-module, real FK) |
| joining_date | DATE | N | – | |
| exit_date | DATE | Y | NULL | ≥ `joining_date`; setting it triggers BR-HR-002 (ADR-008 §5) |
| salary_structure_json | JSON | N | – | Serialized breakdown, e.g. `{"basic":40000}` |
| status | enum (`Active`, `Exited`) | N | Active | BR-HR-002 |

Unique constraint: `employee_code`.

### Lifecycle

Created (onboarding) → Active → Exit Initiated (`exit_date` set) →
Exited (access revoked, BR-HR-002, ADR-008 §5) → Archived. "Exit
Initiated" is not a separate stored status value — `status` is binary
(`Active`/`Exited`) per Appendix-G's own attribute catalogue; `exit_date`
being non-null is itself the "Exit Initiated" signal, and `status`
flips to `Exited` in the same call. BR-HR-006's "Pending Settlement"
intermediate state is out of scope (ADR-008 §9).

---

## Entity: `PayrollRun` (ENT-HR-004, table `payroll_runs`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| employee_id | BIGINT UNSIGNED | N | – | FK → `employees` (intra-module, real FK) |
| pay_period | VARCHAR(10) | N | – | Format `YYYY-MM` |
| gross_pay | DECIMAL(10,2) | N | – | Positive |
| deductions_json | JSON | N | – | Caller-supplied (ADR-008 §8), e.g. `{"PF":4200}` |
| net_pay | DECIMAL(10,2) | N | – | `= gross_pay - sum(deductions_json)`, computed |
| status | enum (`Draft`, `Approved`, `Processed`) | N | Draft | BR-HR-003, BR-HR-007 |

Unique constraint: `(employee_id, pay_period)` (BR-HR-003).

### Lifecycle

Created (Draft, gated by `attendance_closures`, BR-HR-001/ADR-008 §4) →
Approved → Processed (payslip issued, immutable per BR-HR-007/ADR-008
§6) → Archived. "Adjusted (supplementary record)" reversal path is out
of scope (ADR-008 §6) — a `Processed` run is immutable, full stop.

---

## Entity: `LeaveRequest` (ENT-HR-005, table `leave_requests`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| employee_id | BIGINT UNSIGNED | N | – | FK → `employees` (intra-module, real FK) |
| leave_type | enum (`CL`, `SL`, `EL`) | N | – | |
| start_date | DATE | N | – | |
| end_date | DATE | N | – | ≥ `start_date` |
| status | enum (`Pending`, `Approved`, `Rejected`) | N | Pending | BR-HR-004 |
| approver_id | BIGINT UNSIGNED | Y | NULL | FK → Administration's `users` (cross-module, plain FK, validated via `UserService`) |

No unique constraint (per Appendix-G).

### Lifecycle

Created (Pending) → Approved (balance decremented, BR-HR-004, ADR-008
§7) / Rejected → Archived.

---

## Additive table: `attendance_closures` (not in Appendix-G — decided addition, ADR-008 §4)

Owned by `HrPayroll`, written only by Attendance's
`StaffAttendanceService::closePeriod()` (one-way push, never read back
by Attendance).

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| employee_id | BIGINT UNSIGNED | N | – | FK → `employees` |
| pay_period | VARCHAR(10) | N | – | Format `YYYY-MM` |
| closed_at | DATETIME | N | – | |
| closed_by | BIGINT UNSIGNED | N | – | `RequestContext::userId()` at closure time |

Unique constraint: `(employee_id, pay_period)`.

---

## Entity: `StaffAttendanceRecord` (ENT-ATT-002, table `staff_attendance_records`, module `App\Modules\Attendance`)

Extends `App\Core\BaseEntity`. Documented here (not in
`docs/design/attendance/`) because it exists solely to gate this
module's `PayrollRun` — see ADR-008 §3.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| employee_id | BIGINT UNSIGNED | N | – | FK → HR & Payroll's `employees` (cross-module, plain FK, validated via `EmployeeService`) |
| attendance_date | DATE | N | – | |
| state | enum (`Present`, `On Leave`, `Unauthorized`) | N | – | BR-ATT-005 |
| is_reconciled | BOOLEAN | N | FALSE | BR-ATT-005 |

Unique constraint: `(employee_id, attendance_date)`.

### Lifecycle

Created (captured) → Reconciled (cross-checked against `LeaveRequest`,
`StaffAttendanceService::reconcile()`) → Closed (month-end, via
`closePeriod()`, pushes to `attendance_closures`, gates `PayrollRun`) →
Archived. "Closed" is not a stored per-record status — closure is
represented by the presence of a matching `attendance_closures` row for
the employee/period, per ADR-008 §4's one-way-push design.

## Out of scope

- BR-HR-006 Full & Final Settlement (ADR-008 §9 — no `Settlement`/
  `ExitRecord` entity in Appendix-G).
- Rendered payslip / `Document` child entity (ADR-008 §10 — data-only
  payslip via `PayrollRun`'s own fields).
- BR-HR-003/007 reversal/supplementary-adjustment workflow (ADR-008 §6
  — a `Processed` run is simply immutable).
- BR-TT-004/FR-16 Substitution (ADR-008 §11 — unblocked but not pulled
  into this pass).
- BR-ATT-007 Biometric consistency (ADR-006 §10 — unrelated to this
  pass, still no configuration flag anywhere).
