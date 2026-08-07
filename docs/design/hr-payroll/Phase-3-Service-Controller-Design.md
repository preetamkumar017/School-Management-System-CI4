---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, Phase 2, ADR-008
---

# Phase 3 — HR & Payroll Service and Controller Design

## `DepartmentService` / `DesignationService`

Plain CRUD (create/update/get/list), uniqueness per Phase 2. No delete
— Master data, same reasoning as every prior Master entity.

## `EmployeeService`

| Operation | Reason |
|---|---|
| `createEmployee(CreateEmployeeRequest): EmployeeResponse` | Validates `department_id`/`designation_id` (intra-module); `employee_code` uniqueness. |
| `updateEmployee(int $id, UpdateEmployeeRequest): EmployeeResponse` | If `exit_date` transitions null → non-null: sets `status = Exited` and, in the same transaction, looks up the linked `User` via Administration's `UserModel::findByOwner('EMPLOYEE', $id)` and calls `UserService::changeStatus()` to `STATUS_DEACTIVATED` (BR-HR-002, ADR-008 §5); failure rolls back the whole update (fail closed, no async SLA). |
| `getEmployee(int $id): EmployeeResponse` | Plain read — also the cross-module existence-check entry point used by Timetable (ADR-008 §2) and Attendance (ADR-008 §3). |
| `listEmployees(): array` | Plain list. |
| `recordAttendanceClosure(int $employeeId, string $payPeriod, int $closedBy): void` | Writes to `attendance_closures` (ADR-008 §4) — called only by `App\Modules\Attendance\Services\StaffAttendanceService::closePeriod()`, never by HR & Payroll's own code. |

## `PayrollRunService`

| Operation | Reason |
|---|---|
| `createPayrollRun(CreatePayrollRunRequest): PayrollRunResponse` | BR-HR-001: rejects with `BusinessRuleException` (`ATTENDANCE_NOT_CLOSED`) unless `AttendanceClosureModel::existsByEmployeePeriod()` is true for `(employee_id, pay_period)` (ADR-008 §4). BR-HR-003: rejects with `DUPLICATE_PAYROLL_RUN` if `existsByEmployeePeriod()` on `PayrollRunModel` is true. Computes `net_pay = gross_pay - sum(deductions_json)` (ADR-008 §8); `status = Draft`. |
| `approve(int $id): PayrollRunResponse` | `Draft → Approved`. |
| `process(int $id): PayrollRunResponse` | `Approved → Processed` — payslip-issued signal (ADR-008 §10). |
| `updatePayrollRun(int $id, ...): PayrollRunResponse` | Rejects with `BusinessRuleException` (`PAYSLIP_IMMUTABLE`) if `status = Processed` (BR-HR-007, ADR-008 §6). |
| `getPayrollRun(int $id): PayrollRunResponse` | Plain read. |
| `listByEmployee(int $employeeId): array` | Employee's payroll history. |

## `LeaveRequestService`

| Operation | Reason |
|---|---|
| `createLeaveRequest(CreateLeaveRequestRequest): LeaveRequestResponse` | Validates `employee_id`; rejects on date overlap with an existing `Approved` request (FR-35); `status = Pending`. |
| `decide(int $id, DecideLeaveRequestRequest): LeaveRequestResponse` | On `Approved`: computes projected balance = decided annual allocation (CL 12 / SL 10 / EL 15, ADR-008 §7) minus `sumApprovedDaysByEmployeeTypeYear()` minus this request's day count; if negative, requires non-empty `override_reason` (logged via `AuditLog::ACTION_OVERRIDE`) or rejects with `BusinessRuleException` (`INSUFFICIENT_LEAVE_BALANCE`, BR-HR-004). Sets `approver_id = RequestContext::userId()`. |
| `getLeaveRequest(int $id): LeaveRequestResponse` | Plain read. |
| `listByEmployee(int $employeeId): array` | Employee's leave history. |

## `StaffAttendanceService` (module `App\Modules\Attendance`, documented here per ADR-008 §3)

| Operation | Reason |
|---|---|
| `recordAttendance(int $employeeId, string $date, string $state): StaffAttendanceRecordResponse` | Validates `employee_id` via HR & Payroll's `EmployeeService::getEmployee()` (ADR-008 §3); unique on `(employee_id, attendance_date)`. |
| `reconcile(int $employeeId, string $fromDate, string $toDate): void` | BR-ATT-005: marks records in range `state = 'On Leave'`, `is_reconciled = true` for any date covered by an `Approved` `LeaveRequest` (queried via HR & Payroll's `LeaveRequestService`); other records in range simply get `is_reconciled = true`. |
| `closePeriod(int $employeeId, string $payPeriod): void` | Rejects unless every `StaffAttendanceRecord` for that employee/`pay_period` month has `is_reconciled = true`; then calls `EmployeeService::recordAttendanceClosure()` (ADR-008 §4's one-way push) — the only call this Service makes into HR & Payroll beyond the existence check above. |

## Controllers — base path `/api/v1/hr-payroll/...`

| Controller | Base path | Endpoints |
|---|---|---|
| `DepartmentController` | `/departments` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` |
| `DesignationController` | `/designations` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` |
| `EmployeeController` | `/employees` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` |
| `PayrollRunController` | `/payroll-runs` | `POST /`, `POST /{id}/approve`, `POST /{id}/process`, `PATCH /{id}`, `GET /{id}`, `GET /?employee_id` |
| `LeaveRequestController` | `/leave-requests` | `POST /`, `POST /{id}/decide`, `GET /{id}`, `GET /?employee_id` |

`StaffAttendanceRecordController` (`/api/v1/attendance/staff-attendance/...`,
Attendance module's own route group): `POST /`, `POST /reconcile`,
`POST /close-period`, `GET /?employee_id`.

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-008's
resolutions. BR-HR-006 (settlement), rendered payslips, and the
BR-HR-003/007 reversal path are explicitly out of scope, not silently
missing. `TimetableEntryService` and `MarksRecordService`-style
cross-module seam closures (ADR-008 §2) are part of this same
implementation pass, not deferred further.
