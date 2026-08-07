---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, ADR-008
---

# Phase 2 — HR & Payroll Model and DTO Design

Convention: `Department`/`Designation` are Master data (no pagination,
no delete); `Employee` is Master-with-lifecycle (no delete, deactivated
via `exit_date`/`status` instead); `PayrollRun`/`LeaveRequest` are
Transaction data, exposed via scoped listings (by employee), not a bulk
paginated index, matching Fees' `Invoice`/`Payment` precedent.

## `DepartmentModel` / `DesignationModel`

| Method | Purpose |
|---|---|
| `findByName(string): ?Entity` / `existsByName(string): bool` / `...ExceptId(...)` | Business-key uniqueness |

## `EmployeeModel`

| Method | Purpose |
|---|---|
| `findByCode(string): ?Employee` / `existsByCode(string): bool` / `...ExceptId(...)` | `employee_code` uniqueness |
| `findByIdActive(int): ?Employee` | Existence check used by cross-module callers (Timetable, Attendance) — returns null if soft-deleted |

## `PayrollRunModel`

| Method | Purpose |
|---|---|
| `existsByEmployeePeriod(int $employeeId, string $payPeriod): bool` | BR-HR-003 |
| `findByEmployeeId(int $employeeId): array` | Employee's payroll history |

## `LeaveRequestModel`

| Method | Purpose |
|---|---|
| `findByEmployeeId(int $employeeId): array` | Employee's leave history |
| `sumApprovedDaysByEmployeeTypeYear(int $employeeId, string $leaveType, int $year): int` | Input to BR-HR-004's balance computation (ADR-008 §7) — sums `DATEDIFF(end_date, start_date) + 1` across `Approved` rows of that type whose `start_date` falls in `year` |
| `existsOverlappingApproved(int $employeeId, string $startDate, string $endDate): bool` | FR-35's stated date-overlap validation |

## `AttendanceClosureModel` (backs `attendance_closures`, ADR-008 §4)

| Method | Purpose |
|---|---|
| `existsByEmployeePeriod(int $employeeId, string $payPeriod): bool` | BR-HR-001 gate, read by `PayrollRunService::create()` |
| `create(int $employeeId, string $payPeriod, int $closedBy): void` | Written only by `App\Modules\Attendance\Services\StaffAttendanceService::closePeriod()` |

## DTOs

`CreateDepartmentRequest`/`UpdateDepartmentRequest`: `department_name`.
`DepartmentResponse`: `department_id`, `department_name`.

`CreateDesignationRequest`/`UpdateDesignationRequest`: `designation_name`.
`DesignationResponse`: `designation_id`, `designation_name`.

`CreateEmployeeRequest`: `employee_code`, `full_name`, `department_id`,
`designation_id`, `joining_date`, `salary_structure_json`.
`UpdateEmployeeRequest`: `full_name`, `department_id`, `designation_id`,
`salary_structure_json`, `exit_date` (setting this triggers BR-HR-002,
ADR-008 §5 — `employee_code`/`joining_date` immutable post-creation).
`EmployeeResponse`: `employee_id`, `employee_code`, `full_name`,
`department_id`, `designation_id`, `joining_date`, `exit_date`,
`salary_structure_json`, `status`.

`CreatePayrollRunRequest`: `employee_id`, `pay_period`, `gross_pay`,
`deductions_json` (ADR-008 §8 — caller-supplied, validated non-negative).
`PayrollRunResponse`: `payroll_run_id`, `employee_id`, `pay_period`,
`gross_pay`, `deductions_json`, `net_pay`, `status`.

`CreateLeaveRequestRequest`: `employee_id`, `leave_type`, `start_date`,
`end_date`. `DecideLeaveRequestRequest`: `decision` (`Approved`/
`Rejected`), `override_reason` (required only if the decision would
push the balance negative, ADR-008 §7). `LeaveRequestResponse`:
`leave_request_id`, `employee_id`, `leave_type`, `start_date`,
`end_date`, `status`, `approver_id`.

`CloseAttendancePeriodRequest` (Attendance module, consumed by
`StaffAttendanceService::closePeriod`): `employee_id`, `pay_period`.
