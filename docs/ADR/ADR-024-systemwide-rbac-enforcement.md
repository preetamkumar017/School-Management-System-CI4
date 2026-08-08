---
status: Accepted
date: 2026-08-08
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-08 — same delegation ADR-004 through ADR-023 record)
relates-to: ADR-015 (BR-HR-004, first permission check), ADR-018 (BR-FEE-002, second permission check, explicitly declined a "broader sweep"); Company Development Standard §9 (RBAC)
---

# ADR-024: System-wide RBAC enforcement — closing the gap ADR-015/018 both declined

## Context

ADR-015 and ADR-018 each wired one specific, already-named permission check
(BR-HR-004's leave-balance override, BR-FEE-002's payment void/refund) and both
explicitly declined to do a "broader sweep," flagging systematic RBAC as a
separate future effort. That gap was demonstrated concretely on 2026-08-08: an
`Employee` role user carrying only the generic `read` permission was able to —
over the real API, not a hypothetical —
1. `PATCH` another employee's full profile and salary structure,
2. create a leave request on another employee's behalf,
3. list every `User` account in the system (usernames, roles, statuses).

None of these were blocked because, outside ADR-015/018's two checks,
`Config\Filters`' `jwtauth` filter (valid-JWT-only) is the *only* gate on every
mutating and listing endpoint across all 13 modules. `Role.permission_set`
today holds generic, unscoped strings (`read`/`create`/`update`/`delete`) that
are stored but never read by anything — the two exceptions use fully-qualified
strings (`hr_payroll.leave.override`, `fees.payment.void_refund`) checked
directly against `RequestContext::permissionSet()`.

This ADR is the "systematic pass" both prior ADRs deferred, closing it for
every module in one design, per the user's explicit direction to resolve the
gap system-wide, not module-by-module.

## Decision

### 1. Two-tier model: module-manage permission, or ownership

For every module, a caller is authorized to mutate (and, for Administration
specifically, to list/read) a record if **either**:

- **Tier 1 — Manage permission**: their JWT's `permission_set` contains a new,
  module-scoped string `"<module>.manage"` (e.g. `hr_payroll.manage`,
  `fees.manage`, `sis.manage`) — full access to every record in that module,
  the same shape ADR-015/018's fully-qualified strings already established,
  generalized to one string per module instead of one per rule.
- **Tier 2 — Ownership**: the target record has a real owner traceable back to
  the caller's own `User.owner_type`/`owner_ref_id` (Employee/Student/
  Guardian), and the caller *is* that owner. Only applies to entities with a
  genuine per-caller owner; master/reference data (Class, Subject, FeeHead,
  Book, Route, Vehicle, GradingScheme, Department, Designation, etc.) has no
  owner concept and is gated by Tier 1 alone for writes — reads on
  master/reference data stay open to any authenticated user, matching this
  project's existing, unchanged posture (ADR-007 §8/ADR-011).

Per-module ownership mapping (only entities with a real per-caller owner get
Tier 2 at all — everything else is Tier-1-only):

| Module | Owned entities (Tier 2 applies) | Owner resolution |
|---|---|---|
| HR & Payroll | `Employee` (self), `LeaveRequest`, `PayrollRun` (read) | caller's `User.owner_type = EMPLOYEE`, `owner_ref_id = employee_id` |
| SIS | `Student` (self), `StudentGuardianLink`, ID card/photo | `owner_type = STUDENT` (self) or `GUARDIAN` (linked via `StudentGuardianLink.guardian_id`) |
| Fees | `Invoice`, `Payment`, `ScholarshipWaiver` (read only — void/refund stays Tier-1-only per ADR-018, not reopened here) | `Invoice.student_id` → caller's linked Student, or linked Guardian via SIS |
| Admission | `Application` (read only — an applicant has no login in this system; Tier 1 only) | n/a — Tier 1 only |
| Examination | `MarksRecord`/`ReportCard` (read only, own child's) | via SIS Guardian/Student linkage |
| Attendance | `AttendanceRecord`/`StaffAttendanceRecord` (read own) | Student or Employee self |
| Library | `BookIssue`/`Reservation` (self) | `borrower_type`/`borrower_ref_id` already on the entity — direct match, no indirection needed |
| Transport | `TransportAllocation` (read own child's) | via SIS Guardian/Student linkage |
| Communication | `NotificationLog` (read own) | `recipient_type`/`recipient_ref_id` already on the entity |
| Administration | none — `User`/`Role`/`AuditLog` have no self-service shape | **Tier 1 only, including reads** — listing/showing Users or Roles now requires `administration.manage`, closing the demonstrated "Priya listed all 8 users" gap directly |
| Academic, Timetable, Configuration, Document, Reports | none — pure master/reference or composition | Tier 1 only for writes; reads unchanged (already open) |

Write endpoints without a listed owned entity above (e.g. `TimetableEntry`,
`FeeStructure`, `Route`) are gated by `<module>.manage` for every write, same
as before this ADR — no regression, just now actually enforced instead of
JWT-only.

### 2. Existing generic permissions are superseded, not silently dropped

`Role.permission_set`'s old `read`/`create`/`update`/`delete` strings are
replaced with the new module-scoped set on every existing role, in the same
migration that seeds the new strings — not left stale, not requiring the
project owner to manually re-grant access:

- **IT Admin** → every `<module>.manage` string (13 modules) — unchanged
  effective access (already had de facto full access via JWT-only gating).
- **Finance Team** → `fees.manage`.
- **HR Team** → `hr_payroll.manage`.
- **Employee** (the minimal self-service role created 2026-08-08 for
  `priya.iyer`) → no `manage` string — Tier 2 ownership only, exactly the
  restricted shape this ADR exists to enforce.
- The five `SmokeTestRole*` rows (test fixtures from Stage 6d/6e's manual
  smoke-testing, not real roles) are left as-is — not production data, out of
  scope for this migration.

### 3. Enforcement point: a shared Service-layer helper, not per-Controller checks

A new `App\Core\Authz\ModuleAuthorizer` (or equivalent — implementer's exact
naming, matching this codebase's `App\Core\*` conventions) provides one method
each module's Services call at their existing mutation entry points:
`assertManageOrOwner(string $managePermission, string $ownerType, int
$ownerRefId)` — throws `AuthorizationException` (`NOT_AUTHORIZED`, 403) if
neither tier passes. This mirrors ADR-015/018's established
Service-layer-not-Controller enforcement point exactly, and centralizes the
tier-1-or-tier-2 logic in one place instead of duplicating it per module.

### 4. `AuditService` gains the acting user's authorization context implicitly

No change needed — `AuditService::record()` already logs `RequestContext::
userId()` on every write; this ADR adds *whether the write was permitted*,
not a new audit dimension.

## Consequences

- Every module's mutating Service methods gain one new call to the shared
  authorizer at their existing entry point — the largest-surface-area change
  in this codebase's history, spanning all 13 modules in one pass (matching
  this ADR's own explicit "system-wide, not module-by-module" mandate).
- A new migration seeds the module-manage permission strings onto existing
  roles (§2) — existing logins keep working with equivalent access
  immediately after this ships, no separate manual re-grant step.
- Every module's feature test suite needs new tests proving both the
  Tier-1-manage-permission path and the Tier-2-ownership path, plus at least
  one test per module proving a caller with neither is rejected — the
  regression this ADR exists to close must be provably closed, not just
  assumed fixed.
- `docs/development/Backend-Outstanding-Items.md` item 2 ("RBAC — only two
  rules enforced") is resolved by this ADR — update it to reflect closure.
- A caller with an `Employee`-shaped role (like `priya.iyer`) can no longer
  edit another employee, apply leave on another employee's behalf, or list
  all `User` accounts — verified by the reproducing test added to §"Consequences"
  above, matching the exact exploit demonstrated 2026-08-08.

## Addendum (Phase 1 implementation, 2026-08-08)

Phase 1 closes the exact exploit demonstrated in Context: Administration
(User/Role/AuditLog) and HR & Payroll, the two areas the exploit spanned.
The remaining 11 modules — **Academic, Admission, SIS, Examination,
Timetable, Attendance, Fees, Library, Transport, Communication, Reports** —
are explicitly untouched by this phase and remain open for a Phase 2 pass,
starting from this same `ModuleAuthorizer` and the per-module table in §1.

### (a) `ModuleAuthorizer` — exact shape shipped

`App\Core\Authz\ModuleAuthorizer` (`backend/app/Core/Authz/ModuleAuthorizer.php`),
constructed with `UserModel` (the same lookup shape
`EmployeeService::revokeAccess()` already used for `UserModel::findByOwner()`),
registered as a shared service via `Config\Services::moduleAuthorizer()`:

- `assertManage(string $managePermission): void` — Tier 1 only, for
  entities with no ownership concept (Administration's User/Role/AuditLog;
  any module's master/reference-data writes). Throws
  `AuthorizationException('NOT_AUTHORIZED', ..., 403)` if the caller's JWT
  `permission_set` doesn't carry `$managePermission`.
- `assertManageOrOwner(string $managePermission, string $ownerType, int
  $ownerRefId): void` — Tier 1 (manage permission) OR Tier 2 (the caller's
  own `User.owner_type`/`owner_ref_id`, looked up via
  `RequestContext::userId()`, matches `$ownerType`/`$ownerRefId`). Same
  exception on failure.

Both methods are called from the Service layer at each method's existing
entry point, exactly matching ADR-015/018's precedent (never from a
Controller).

Two internal-call escape hatches were needed and are documented at their
call sites: `UserService::changeStatusInternal()` (ungated, used only by
`EmployeeService::revokeAccess()`'s BR-HR-002 exit-triggered deactivation,
which runs under the acting HR caller's `hr_payroll.manage`, not
`administration.manage`) and a handful of ungated existence/count helpers
(`EmployeeService::assertEmployeeExists()`/`countEmployees()`,
`UserService::assertUserExists()`/`countUsers()`,
`DepartmentService::countDepartments()`,
`DesignationService::countDesignations()`) for cross-module
Service-to-Service calls (`StaffAttendanceService`, `CircularService`,
`NotificationLogService`, `ReportsService::getSummary()`) that were
validating a foreign key or computing an aggregate count, not performing a
user-facing read gated by the target module's own RBAC.

### (b) `EmployeeService::updateEmployee()` — Tier 1 only, not Tier 2

ADR-024 §1's table lists `Employee (self)` under HR & Payroll's Tier-2-owned
entities. Implemented instead as `hr_payroll.manage`-only, with no
ownership fallback even for the employee's own record. Reasoning: every
field on `UpdateEmployeeRequest` (`full_name`, `department_id`,
`designation_id`, `salary_structure_json`, `exit_date`) is HR-administrative
— none are safe for self-service editing under real-ERP conventions (an
employee must never be able to self-promote, reassign their own department,
or raise their own salary). A literal Tier-2 reading of the table would have
left the update endpoint itself exploitable by the exact record owner it was
meant to protect. `getEmployee()` (single-record read) correctly keeps Tier
2 — reading your own profile is safe self-service and is what the "My HR"
frontend page needs; `updateEmployee()` does not have an equivalent safe
subset of fields, so it took the narrower interpretation.

### (c) Scope confirmation

This phase touched only `App\Modules\Administration` (`UserService`,
`RoleService`, `AuditService`'s two read methods) and
`App\Modules\HrPayroll` (`EmployeeService`, `LeaveRequestService`,
`PayrollRunService`, `DepartmentService`, `DesignationService`), plus the
minimal cross-module call-site fixes listed in (a) needed to keep
`Attendance`, `Communication`, and `Reports` working against the newly
gated Administration/HR & Payroll methods they already called into.
No other module's own Service methods gained a new authorization check in
this phase. Phase 2 starts from the 11 modules named above.

## Addendum (Phase 2 implementation, 2026-08-08)

Phase 2 completes system-wide RBAC enforcement across all remaining 11 modules:
**Academic, Admission, SIS, Examination, Timetable, Attendance, Fees, Library,
Transport, Communication, Reports**, extending the exact `ModuleAuthorizer`
pattern established in Phase 1.

### (a) Reports Read-Gating Decision

All public methods in `ReportsService` (`getSummary()`, `getFeeCollectionSummary()`,
`getAttendanceOverview()`, `getAdmissionsFunnel()`, `getAcademicPerformance()`) are
gated by Tier 1 `reports.manage`.

**Reasoning**: While single-record master data reads (e.g. `getClass()`, `getBook()`,
`getRoute()`) remain open to authenticated users per ADR-007 §8/ADR-011, Reports' methods
compute aggregate statistics that span school-wide datasets across all students and employees
(e.g., total fee collections, defaulter counts, attendance percentages across classes, exam
gpa distributions, and full-school system counts). Exposing these aggregate summaries to callers
without `reports.manage` would present a significant data exposure risk. Therefore, Reports' reads
are explicitly gated by `reports.manage`.

### (b) SIS Guardian Login Reachability Decision

No code path in this codebase creates, authenticates, or resolves a `User` record with
`owner_type = GUARDIAN`. User accounts are created exclusively for staff/employees and
students.

**Reasoning**: Attempting to wire Tier 2 ownership checks for Guardian logins across SIS,
Fees, Examination, Transport, and Communication would add unreachable code paths that cannot
be exercised by real callers. Consequently, Guardian-facing administrative management actions
are gated by Tier 1 `<module>.manage`, and student-related reads resolve Tier 2 ownership
through the student's own `User` account (`owner_type = STUDENT`, `owner_ref_id = student_id`).

### (c) Per-Module Enforcement Summary & Judgment Calls

- **Academic**: Gated by `academic.manage` (Tier 1) for all write mutations across `AcademicSessionService`, `ClassService`, `ClassSubjectMapService`, `GradingSchemeService`, `SectionService`, and `SubjectService`. Master data reads remain open.
- **Admission**: Gated by `admission.manage` (Tier 1) for application creation, verification, shortlisting, waitlisting, rejection, hold release, and enrollment confirmation in `ApplicationService` and `SeatAllocationService`. Applicants do not possess user logins; Tier 1 only.
- **SIS**: Gated by `sis.manage` (Tier 1) for student creation, profile updates, section transfers, status changes, photo uploads, ID card issuance, guardian creation, and student-guardian link management in `StudentService`, `GuardianService`, and `StudentGuardianLinkService`. Tier 2 (`STUDENT` owner) allows students to read their own record via `StudentService::getStudent()`.
- **Examination**: Gated by `examination.manage` (Tier 1) for exam management, marks entry/verification, publishing, and report card generation/recalculation in `ExamService`, `MarksRecordService`, `PromotionService`, and `ReportCardService`. Tier 2 (`STUDENT` owner) allows students to read their own marks (`MarksRecordService::getMarksRecord()`) and report cards (`ReportCardService::getReportCard()`, `generatePdf()`). Cross-module aggregate read `listReportCardsByExamForCrossModuleRead()` is exposed for `ReportsService` under `reports.manage`.
- **Timetable**: Gated by `timetable.manage` (Tier 1) for timetable entries, teacher eligibility mapping, and substitution assignments across `TimetableEntryService`, `SubjectTeacherEligibilityService`, and `SubstitutionService`.
- **Attendance**: Gated by `attendance.manage` (Tier 1) for recording student/staff attendance. Tier 2 allows self-reading student attendance (`AttendanceService::getAttendanceRecord()`) and staff attendance (`StaffAttendanceService::getStaffAttendance()`).
- **Fees**: Gated by `fees.manage` (Tier 1) for fee heads, fee structures, creating/voiding invoices, recording/refunding payments, and scholarship waivers in `FeeHeadService`, `FeeStructureService`, `InvoiceService`, `PaymentService`, and `ScholarshipWaiverService`. Per ADR-018, void and refund entry points remain strictly Tier 1 only. Tier 2 (`STUDENT` owner) allows reading invoices, payments, and waivers.
- **Library**: Gated by `library.manage` (Tier 1) for book catalog updates and circulation desk operations (`issueBook`, `returnBook`, settlement) in `BookService` and `BookIssueService`. Borrowers (`STUDENT`/`EMPLOYEE`) are allowed Tier 2 access for self-service reservation management (`createReservation`, `cancelReservation`, `getReservation`, `listByBorrower`) and issue history reads (`getBookIssue`, `listByBorrower`).
- **Transport**: Gated by `transport.manage` (Tier 1) for vehicles, routes, drivers, allocations, and trip starts in `VehicleService`, `RouteService`, `DriverService`, `TransportAllocationService`, and `TripService`. Tier 2 (`STUDENT` owner) allows students to view their own allocation via `TransportAllocationService::getAllocation()`.
- **Communication**: Gated by `communication.manage` (Tier 1) for publishing/archiving circulars and manual notification logs/dispatches in `CircularService` and `NotificationLogService`. `NotificationLogService::createInternal()` remains ungated for automated cross-module system triggers. Tier 2 allows recipients to view their own notification log via `getNotificationLog()`.
- **Reports**: Gated by `reports.manage` (Tier 1) across all public methods in `ReportsService`.

### (d) Role Permission Seeding Verification

Direct query against the dev database (`school_erp_dev`) confirmed that `IT Admin`'s `permission_set` already includes all 13 `<module>.manage` permission strings as seeded by Phase 1 migration `2026-08-08-110001_SeedModuleManagePermissions.php`. `Finance Team` carries `fees.manage` and `HR Team` carries `hr_payroll.manage`. `Employee` carries no `manage` permissions, restricting non-administrative staff to Tier 2 ownership access. No additional database migration was required for Phase 2.
