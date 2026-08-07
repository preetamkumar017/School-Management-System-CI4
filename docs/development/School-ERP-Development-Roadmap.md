---
status: Active
last-updated: 2026-08-07
references: Company Development Standard, School-ERP-Module-Architecture.md, docs/design/academic, docs/design/admission, docs/design/sis
---

# School ERP — Development Roadmap

## Purpose

Sequences actual implementation now that Academic, Admission, and SIS design
is fully approved with zero open items (ADR-004) and the Company Development
Standard is in place. This is the Implementation stage of the project's own
rule: Requirement → Design → Approval → **Implementation** → Verification →
Commit. It does not re-decide anything already settled in `docs/design/` or
`docs/ADR/` — it only sequences building it.

## Gap found while planning this roadmap: Administration needed a design pass first — done

Core infrastructure (JWT auth, RBAC, audit logging) depends on `User`,
`Role`, and `AuditLog` tables that the **Administration** module owns
(`School-ERP-Module-Architecture.md`) — but Administration had no
Phase-style design work at all when this roadmap was first drafted. Unlike
Attendance, Fees, or the other undesigned modules, this one actually blocks
everything else: nothing in `App\Core` can be implemented against a real
table until `User`/`Role`/`AuditLog` are designed. **Resolved same day**:
`docs/design/administration/Phase-1` through `Phase-6` now cover this
minimal slice — see Stage 1 below, which reflects the completed design.

## Environment note

Company Development Standard targets PHP 8.3+. This machine currently has
PHP 8.2.29 (`php -v`) via Homebrew. CodeIgniter 4 runs fine on 8.2 for local
development, so this doesn't block Stage 0, but it should be reconciled
before production deployment targeting Hostinger — either upgrade local PHP
to 8.3+ (`brew upgrade php`) or confirm Hostinger's actual available PHP
version and adjust the standard if 8.3 isn't offered. Flagging rather than
silently deciding, since upgrading PHP is a machine-wide change outside this
project's scope.

## Stage 0 — Project bootstrap — done (2026-08-06)

- `git init` — done; first commit made.
- Top-level structure: `backend/`, `database/`, `mobile/`, `docs/` (Company
  Development Standard §1) — done.
- CI4 skeleton via `composer create-project codeigniter4/appstarter backend`
  — done (CodeIgniter 4.7.4).
- `App\Core\*` (Auth, RBAC, Audit, Response, Exceptions, Logging,
  Notification, Document, Config) and `App\Modules\` directory skeleton
  created under `backend/app/` — empty, ready for Stage 1/2 implementation.
- `.env` created from the CI4 template (gitignored, never committed with
  real values).
- PHPStan (with `codeigniter/phpstan-codeigniter` for framework-aware
  analysis — needs `bootstrapFiles: vendor/codeigniter4/framework/system/Test/bootstrap.php`
  to resolve CI4's constants/helpers) and PHP_CodeSniffer (PSR-12 via
  `phpcs.xml`) wired as `composer lint` / `composer analyse`; `composer ci`
  runs lint + analyse + test together. Both pass cleanly against the fresh
  skeleton.
- PHPUnit scaffold present (CI4 default), 5/5 sample tests pass.
- **Known local environment gap, not fixed:** no code-coverage driver
  (xdebug/pcov) installed locally — `pecl install pcov` failed (no release
  available for this PHP build) and wasn't chased further, since it's a
  local-dev-experience nicety, not a functional blocker. PHPUnit still runs
  and reports pass/fail correctly; it just can't produce a coverage report
  yet. Revisit if/when coverage reporting is actually needed.
- Local PHP is 8.2.29 vs. the Company Standard's 8.3+ target — `composer.json`
  currently declares `"php": "^8.2"` so local dev works; reconcile with
  Hostinger's actual available version before this matters for real (see
  Environment note above).
- First commit made, covering the full design doc set plus this skeleton.

## Stage 1 — Administration (minimal slice) — done (2026-08-06)

Implemented together with Stage 2 (Core) in practice — Administration's
entities are declared to extend `App\Core\BaseEntity`, so the two were never
really separable in build order; Core's structural pieces (base classes,
exceptions, response envelope) got built first as a prerequisite, then
Administration's concrete `User`/`Role`/`AuditLog`/`refresh_tokens` on top.

- Migrations: `roles`, `users`, `audit_logs`, `refresh_tokens` — run and
  verified against a real MySQL 8 database (`school_erp_dev`).
- Entities/Models: `User`, `Role`, `AuditLog`, `RefreshToken`.
- Services: `AuthService` (login with lockout after 5 attempts +
  anti-enumeration, refresh, logout/logout-all, change-password revoking all
  sessions), `UserService`, `RoleService`, `AuditService` (the one write path
  for the audit trail — every other module's Service layer calls this, never
  `AuditLogModel` directly).
- Controllers + routes: `/api/v1/auth/*`, `/api/v1/administration/{users,roles,audit-logs}`.
- `JwtAuthFilter` (Bearer token validation, populates `RequestContext` with
  user/role/permission-set) and `RequestContextFilter` (request_id), wired
  in `Config\Filters`.
- Verification: 20 passing PHPUnit feature tests, **plus** a full manual
  smoke test by hand against the dev server (login, wrong-password/
  nonexistent-user anti-enumeration, 5-attempt lockout, refresh, logout
  revocation, role/user CRUD, audit trail retrieval) — this caught two real
  bugs the automated suite's mocked/simulated request cycle didn't exercise
  the same way: `User.last_login_at` wasn't cast to a `Time` object, and
  JSON columns (`permission_set`, `old_value`/`new_value`) needed explicit
  `json_encode()` before insert since CI4's Query Builder binds a raw PHP
  array as a SQL tuple, not JSON. Both fixed.
- `composer lint`/`analyse`/`test` all pass (test suite has one persistent
  warning, not an error — see Stage 0's coverage-driver gap, still open).

## Stage 2 — Core infrastructure (`App\Core`) — done (2026-08-06)

Built alongside Stage 1, against Stage 1's real tables, not stubs:

- `BaseEntity` / `BaseModel` — common audit columns (`created_by`/
  `updated_by`/`is_deleted`/`deleted_by`, auto-stamped via Model callbacks),
  soft-delete (CI4's native `deleted_at` handling, extended to also set
  `is_deleted`/`deleted_by` in the same query — no framework hook exists for
  that combination, so `BaseModel::doDelete()` overrides it). `version`-column
  optimistic locking is supported per-model where a table declares it, not
  forced globally (Company Development Standard §4.4 — only tables with real
  write contention need it; none in this slice do).
- `BaseController` + `ApiResponseTrait` — the standard response envelope (§7).
- `JwtManager` (`App\Core\Auth`) — pure JWT issuance/decode, no DB access.
- `PermissionChecker` (`App\Core\RBAC`) — pure permission-set check, reads
  straight from the validated JWT payload, never re-queries `Role` per
  request (§9 — a permission change takes effect on next login/refresh).
- `ApiException` and its five subclasses (Validation/BusinessRule/
  Authorization/Concurrency/RateLimit) + `ApiExceptionHandler`, wired into
  `Config\Exceptions::handler()` — every response, success or error, uses
  the one envelope shape. The sixth category (System/Unhandled) has no
  dedicated class by design — anything not one of the five is System.
- `RequestContext` (`App\Core\Http`) — static per-request holder for
  request_id/user_id/role_id/permission_set, set by the two Filters, read by
  `BaseModel`'s audit-column stamping and `ApiExceptionHandler`.
- Not yet built: `Notification`, `Document`, `Config` under `App\Core` —
  nothing depends on them yet, same reasoning as Administration's deferred
  entities.
- **Verification gate met**: the full login → protected-route → audit-log-
  written path works end to end (both automated and manual verification,
  see Stage 1).

## Stage 3 — Academic module implementation — DONE (2026-08-06)

Reference: `docs/design/academic/Phase-1` through `Phase-6` (fully
approved, no dependency beyond Core).

- Migrations: `academic_sessions`, `classes`, `sections`, `subjects`,
  `grading_schemes`, `class_subject_map` — all six applied.
- Entities/Models/Services/Controllers per Phase 2–5, all six verticals
  (`AcademicSession`, `Class`, `Section`, `Subject`, `GradingScheme`,
  `ClassSubjectMap`) end to end. `Class` the entity is named `AcademicClass`
  in code — `class` is a PHP reserved word; Model/Service/Controller/DTO
  names keep the doc's own "Class" naming since those aren't reserved.
  Response DTOs are real classes (Phase 3's design, a deliberate departure
  from Administration's inline-array convention), each wrapping an Entity
  with a `toArray()` used at the Controller boundary.
- Verification: 19 new PHPUnit feature tests in
  `backend/tests/Feature/Academic/` (39 total with Administration's),
  covering full CRUD plus every named business rule (session name/date
  overlap, forward-only status transitions, class name/sequence-order
  uniqueness, section name uniqueness within a class, subject code
  uniqueness, grading-scheme overlapping-band rejection, duplicate
  class-subject mapping rejection) — including Phase 6's closure-criteria
  test, `GradingScheme` immutability once locked by a closed exam
  (exercised directly against the Service with a stubbed
  `isReferencedByClosedExam`, since Examination itself isn't designed yet).
  Also manually smoke-tested end-to-end against the real dev server (all 15
  endpoints, including the validation/business-rule error paths and the
  regenerated `/docs/` Swagger UI) — no gaps found this time, unlike Stage
  1+2's `last_login_at`/JSON-column surprises.
- `GradingSchemeModel::isReferencedByClosedExam` always returns `false` for
  now — there is no `exams` table yet (Examination is Stage 6, undesigned).
  This is documented in the Model's own docblock, not a silent gap.

## Stage 4 — Admission module implementation — DONE (2026-08-06)

Reference: `docs/design/admission/Phase-1` through `Phase-7` (fully
approved). Depends on Stage 3 (`Class`, `AcademicSession`).

- Migrations: `applications`, `seat_allocations` — both applied.
- Entities/Models/Services/Controllers per Phase 2–5: `Application`
  (create, verify, shortlist, waitlist, reject, get, list-paginated) and
  `SeatAllocation` (create, updateCapacity, get, findForClassAndSession).
  **Scope note, decided proactively:** Phase 4/5 explicitly exclude the
  `SUBMITTED/VERIFIED/... → ADMITTED` transition (FR-02 Confirm
  Enrollment, Phase 6) from Admission's own Controller/Service design —
  it depends on SIS's `StudentService::createStudentStub`, which doesn't
  exist until Stage 5. There is no circular dependency: Stage 4 ships
  everything Phase 4/5 actually scope to it, and `SeatAllocationModel::
  incrementSeatsFilled` (the pessimistic-lock counter method Phase 6's
  orchestration will call) is implemented now, ready for Stage 5 to wire
  up. `application_reference_no` generation (format `APP-{year}-{5
  digits}`) isn't specified beyond the example in Phase 1 — implemented
  as candidate-generation-with-retry against the DB's own unique
  constraint as the final backstop, not a designed algorithm. Aadhaar
  checksum (BR-ADM-006) uses the Verhoeff algorithm (the real Aadhaar
  standard, not Luhn).
- Verification: 12 new PHPUnit tests (51 total), including the
  headline test this stage's own bullet asked for —
  `SeatAllocationConcurrencyTest` proves the pessimistic row-lock on
  `SeatAllocation`'s counters is a genuine database row lock (a second,
  independent connection blocks on `SELECT ... FOR UPDATE` while the
  first transaction is open, verified via a short `innodb_lock_wait_timeout`
  rather than real thread concurrency) and that the guarded increment
  never lets `seats_filled` exceed `total_capacity`. Also manually
  smoke-tested end-to-end against the real dev server (all 8 endpoints,
  including the Verhoeff-checksum validation and RTE-ceiling error
  paths).

## Stage 5 — SIS module implementation — DONE (2026-08-06)

Reference: `docs/design/sis/Phase-4.2` through `Phase-7`, `Phase-5`
Implementation Plan (fully approved). Depends on Stage 3 (`Section`) and
Stage 4 (`createStudentStub` is called from Admission).

- Migrations: `students`, `guardians`, `student_guardian_link` — all
  applied. `student_guardian_link` uses a real DB-level FK (unlike
  Admission's/Academic's cross-module references) — both `Student` and
  `Guardian` live inside SIS, so this is intra-module.
- Entities/Models/Services/Controllers per Phase 4.2–4.7, all three
  verticals (`Student`, `Guardian`, `StudentGuardianLink`) end to end.
  SIS is the one module that keeps a dedicated Mapper class per entity
  (`App\Modules\Sis\Mappers`) — Academic/Admission skip that layer as a
  deliberate scope reduction, but SIS's own approved design keeps it, so
  it's implemented as designed: `StudentMapper`/`GuardianMapper` mutate a
  fetched Entity in place (`Model::save($entity)`), a different pattern
  from every other module's `Model::update($id, array)` calls — both are
  legitimate CI4 idioms, and this is the one place the codebase uses the
  Entity-mutation style, matching what Phase 4.5 actually specifies.
  `StudentController` has no `POST /` route — a stub is only ever created
  via `createStudentStub`, called from Admission (ADR-004 §3), never a
  public endpoint.
- **This stage also completed Admission's Stage-4-deferred FR-02 Confirm
  Enrollment** (`docs/design/admission/Phase-6`): `ApplicationService::
  confirmEnrollment` — re-validates seat/RTE capacity and duplicate
  Aadhaar identity, generates the Admission Number, calls `StudentService::
  createStudentStub`, and transitions `Application.status → ADMITTED`, all
  inside one manually-managed transaction (`Config\Database::connect()`,
  `transStart`/`transRollback` in a catch block/`transComplete`) that
  correctly nests with `SeatAllocationModel::incrementSeatsFilled`'s own
  internal `transStart`/`transComplete` (CI4's `transDepth` counter only
  commits/rolls back at the outermost call — verified this nests
  correctly, not just assumed). New endpoint:
  `POST /api/v1/admission/applications/{id}/confirm-enrollment` — no
  route name was specified in any design doc, so this was decided
  directly (matches the existing `POST .../{id}/<sub-action>` convention
  every other Admission/Academic transition endpoint already uses).
- Verification: 20 new PHPUnit tests (68 total), including **the full
  Confirm Enrollment integration test ADR-004 specifically required** —
  `ConfirmEnrollmentTest::testForcedSisFailureRollsBackSeatCountAndApplicationStatusAtomically`
  forces a real SIS-side failure (a `Student` row pre-existing for the
  same `application_id`, tripping `createStudentStub`'s own BR-SIS-002
  guard) and proves the seat-count increment, the `Application.status`
  transition, and `decided_at` all roll back together — this was the
  single most important test in the whole system so far, the one
  everything else was blocked behind before ADR-004. Also manually
  smoke-tested the full flow end-to-end against the real dev server:
  create → verify → shortlist → seat allocation → confirm-enrollment →
  student created (DRAFT, correct `admission_number`/`application_id`,
  `section_id` null) → seat count incremented → section-transfer →
  activation blocked without a guardian → activation succeeds once a
  guardian is linked.
  One incidental finding from the dev-server run, not a Stage 5 bug:
  `AcademicSessionService::getCurrentActiveSession()` returns whichever
  `ACTIVE` session `findByStatus()` finds first if more than one row is
  `ACTIVE` — nothing in Academic's own design enforces "only one session
  ACTIVE at a time," so this is a pre-existing latent gap in Stage 3's
  scope, not something Stage 5 introduced or is positioned to fix.

## Stage 6a — Examination module implementation — DONE (2026-08-06)

Reference: `docs/design/examination/Phase-1` through `Phase-5` (designed
and implemented in the same session, per ADR-005 — see below). Closes
ADR-002's deferred BR-SIS-004 (Historical Record Immutability). Depends on
Academic (`Class`, `AcademicSession`, `GradingScheme`, `Subject`) and SIS
(`Student`), both already built.

- **Design pass first**: `docs/ADR/ADR-005-examination-module-scope-decisions.md`
  resolves everything Appendix-C/E left unspecified before any code was
  written — BR-SIS-004's governing entity, BR-EXM-005's Attendance
  dependency (stubbed, Attendance doesn't exist), `PromotionRecord.
  fee_closure_confirmed`'s Fees dependency (caller-supplied, Fees doesn't
  exist), the GPA formula (`min(9.99, percentage/10)` averaged, clamped to
  fit the approved `DECIMAL(3,2)` column), class rank (standard
  competition ranking), the BR-EXM-006 anomaly threshold (30 percentage
  points, documented default), report-card scope (data record only, no
  PDF — `Document`/PDF tooling don't exist), and re-evaluation (a single
  logged action, not an `ApprovalRequest` workflow — that entity doesn't
  exist either). `docs/design/School-ERP-Module-Architecture.md`'s
  Examination row updated to Designed.
- **Architecture correction caught before implementation**: the obvious
  fix for `GradingSchemeModel::isReferencedByClosedExam` (query
  Examination's `exams` table) would have made Academic depend on
  Examination while Examination already depends on Academic — a cycle.
  Resolved the same way ADR-003 resolved Admission↔SIS: Academic gained
  an additive `locked_by_closed_exam` column and a new
  `GradingSchemeService::lockSchemeReferencedByClosedExam` method;
  Examination calls it (the already-established direction) when
  `ReportCardService::publishReportCards` closes an `Exam`. The
  dependency stays one-way. See ADR-005 §10.
- Migrations: `exams`, `marks_records`, `report_cards`, `promotion_records`
  — all applied, plus one additive column on Academic's `grading_schemes`.
- Entities/Models/Services/Controllers per Phase 2–5, all four verticals
  (`Exam`, `MarksRecord`, `ReportCard`, `PromotionRecord`). `ExamService::
  lockExam` computes grade/GPA/class-rank and upserts `ReportCard` rows in
  one transaction with the status transition (FR-19); `ReportCardService::
  publishReportCards` gates on BR-EXM-001, publishes, closes the `Exam`,
  and locks the `GradingScheme`. `MarksRecordService::reevaluate` is the
  single logged re-evaluation action (BR-EXM-003/004). A shared
  `ClosedSessionGuard` trait (intra-module only) enforces BR-SIS-004
  across all four Services, requiring `override_reason` once a record's
  `AcademicSession` is `CLOSED`/`ARCHIVED`, logged via
  `AuditLog::ACTION_OVERRIDE` — the same mechanism Administration already
  built, reused rather than duplicated.
- Verification: 15 new PHPUnit tests (83 total) — GPA/rank calculation
  against known inputs, BR-EXM-002 range validation, BR-EXM-003 lock/
  re-evaluate, BR-EXM-006 anomaly flagging (a historical 90% mark vs. a
  new 20% mark, correctly flagged and blocking lock until re-evaluated),
  BR-EXM-001 publish gating, BR-SIS-001 promotion closure gating, and
  BR-SIS-004 closed-session immutability (rejected without
  `override_reason`, succeeds with one). Also manually smoke-tested the
  full flow end-to-end against the real dev server: exam create →
  activate → marks entry → lock (grade `A1`, GPA `9.5`, computed
  correctly) → publish → confirmed the referenced `GradingScheme` is now
  genuinely locked against further mutation via a real HTTP `PATCH`
  attempt.

## Stage 6b — Timetable and Attendance implementation — DONE (2026-08-06)

Reference: `docs/design/timetable/Phase-1` through `Phase-3` and
`docs/design/attendance/Phase-1` through `Phase-3`, per
`docs/ADR/ADR-006-timetable-and-attendance-scope-decisions.md`.

- **The roadmap's own assumption was wrong, caught during the design
  pass**: Stage 6b was originally planned as "Attendance + Timetable, no
  inter-dependency between the two." Appendix-G's `AttendanceRecord` entity
  card lists `timetable_entry_id → TimetableEntry` as a Foreign Key —
  Attendance genuinely depends on Timetable. Built Timetable first,
  Attendance second, in the same pass, per ADR-006.
- **A second, deeper dependency surfaced and was deliberately not
  chased**: `TimetableEntry.employee_id → Employee` belongs to HR &
  Payroll, undesigned and not scheduled. Building `Employee` just to
  satisfy one FK would have silently expanded this stage into a second,
  unplanned module. ADR-006 §1 stubs it instead (stored, not validated) —
  same shape as every other undesigned-module dependency this project has
  hit (Attendance/Fees for Examination, ADR-005). `StaffAttendanceRecord`
  (ENT-ATT-002) is deferred entirely, not stubbed — its whole reason to
  exist (payroll reconciliation) has no meaning without `Employee`/`Leave`
  (ADR-006 §2). BR-TT-003/004 (lab capacity, substitution) are out of
  scope — no `Room` capacity entity, no `Substitution` entity exists
  anywhere in the approved catalogue (ADR-006 §3, §4).
- Migrations: `timetable_entries`, `attendance_records` — both applied.
  `TimetableEntry` DB-enforces BR-TT-001 (`(employee_id, day_of_week,
  period_no)` unique) and half of BR-TT-002 (`(section_id, day_of_week,
  period_no)` unique); room double-booking is a Service-layer check
  (`room_id` isn't in Appendix-G's own composite-index list). BR-TT-005
  (publication lock) is a **decided** in-place mutation with
  `version_no` increment, not a parallel history-row scheme — no versions
  table exists anywhere in this codebase's design and `AuditLog` already
  captures full before/after values. BR-TT-006 (weekly load ceiling)
  implemented with a decided default (30 periods/week).
- **Closed the seam ADR-005 §2 deliberately left open**: `AttendanceService::
  isExamEligibilityAtRisk` (BR-ATT-006, 75% threshold, decided default) is
  now called for real by Examination's `MarksRecordService::
  createMarksRecord` — a new, one-way Examination → Attendance dependency
  (allowed: Attendance has no dependency back on Examination). A flagged
  student can still be marked, but only with `override_reason`, logged via
  `AuditLog::ACTION_OVERRIDE`, exactly matching BR-EXM-005's "without
  Academic Head override" text.
- BR-ATT-002/003 (attendance lock + correction) reuse the exact
  `override_reason` + `AuditLog::ACTION_OVERRIDE` shape Examination's
  re-evaluation established (ADR-005 §7) — decided edit-window boundary:
  same calendar day as `attendance_date` only.
- Verification: 13 new PHPUnit tests (96 total) — BR-TT-001/002 double-
  booking rejection, BR-TT-005 version increment, BR-TT-006 load-ceiling
  enforcement (30 real inserts then a rejected 31st), BR-ATT-001 duplicate
  rejection, BR-ATT-002/003 same-day-vs-override correction, BR-ATT-006
  percentage calculation, and the cross-module integration test proving
  Examination's marks-entry gate genuinely blocks (and then accepts with
  override) an at-risk student based on real `AttendanceRecord` data.
  Manually smoke-tested end-to-end against the real dev server: entry
  create → publish → attendance mark → percentage query → double-booking
  rejection, all verified over real HTTP.

## Stage 6c — Fees module implementation — DONE (2026-08-06)

Reference: `docs/design/fees/Phase-1` through `Phase-3`, per
`docs/ADR/ADR-007-fees-module-scope-decisions.md`. Depends only on
Academic (`Class`, `AcademicSession`) and SIS (`Student`) — Appendix-G's
FK graph was checked first, per Stage 6b's own lesson, and confirmed
clean this time (no hidden HR/Communication chain).

- **Design pass first**: ADR-007 resolves BR-FEE-003 (Transport
  auto-linkage, undesigned, deferred), BR-FEE-004/008 (late fee/defaulter
  — no scheduler infrastructure exists anywhere in this codebase, so both
  are explicit-trigger Service methods, not cron jobs, with decided
  defaults: 5% late fee, overdue = past due date and unpaid), BR-FEE-005
  (RTE waiver — automatic *application* at invoice time, manual
  *creation*, since the "waived fee-head list" configuration source
  doesn't exist), BR-FEE-006 (implemented for real — DB unique
  constraint), BR-FEE-007 (GST — not implemented, no line-item entity
  exists in Appendix-G to itemize against), and BR-FEE-002 (Finance-Team-
  only refund/void — not enforced, matching this codebase's consistent,
  pre-existing posture of never having wired `PermissionChecker` into any
  Controller in any prior stage). `docs/design/School-ERP-Module-
  Architecture.md`'s Fees row updated to Designed.
- **`Invoice` has no persisted line-item breakdown** — Appendix-G's own
  entity card has no such child entity, only a `total_amount` described
  as "sum of line items minus waivers." `InvoiceService::generateInvoice`
  computes this server-side (sums matching `FeeStructure` rows for the
  student's resolved class/session/category, minus matching
  `ScholarshipWaiver`s) and persists only the total — matching the
  approved shape exactly, no invented entity.
- A decided additive column, `Invoice.late_fee_applied` (not in
  Appendix-G's literal attribute list), tracks late-fee idempotency — the
  same kind of decided addition as Academic's `locked_by_closed_exam`
  (ADR-005 §10).
- Migrations: `fee_heads`, `fee_structures`, `invoices`, `payments`,
  `scholarship_waivers` — all applied.
- Verification: 14 new PHPUnit tests (110 total) — the headline test
  (`InvoiceTest::testGenerateInvoiceComputesTotalFromFeeStructureMinusWaivers`)
  proves the full computation end to end against real `FeeStructure`/
  `ScholarshipWaiver` data, not just presence/shape assertions. Also
  covers BR-FEE-001 (payment locks the invoice, void/refund never reopen
  it), BR-FEE-006 (duplicate gateway reference rejected), and the
  late-fee/defaulter explicit triggers. Manually smoke-tested the full
  flow end-to-end against the real dev server: fee head → fee structure →
  student section-transfer → invoice generation (computed total verified)
  → full payment → invoice locked/`PAID` → duplicate reference correctly
  rejected.

## Stage 6d — HR & Payroll implementation — DONE (2026-08-07)

Reference: `docs/design/hr-payroll/Phase-1` through `Phase-3`, per
`docs/ADR/ADR-008-hr-payroll-module-scope-decisions.md`. Depends only on
Academic/SIS (transitively, via Timetable/Attendance) and Administration's
`User` — Appendix-G's FK graph was checked first, per Stage 6b's own
lesson, and confirmed clean (no Academic/SIS/Examination dependency).

- **Design pass first**: ADR-008 resolves BR-HR-001 (attendance-closure
  precondition — implemented via a one-way push from Attendance into a
  new HR-owned `attendance_closures` table, mirroring ADR-005 §10's
  `locked_by_closed_exam` pattern, rather than a live cross-module read
  that would have completed a cycle), BR-HR-004 (leave balance — decided
  annual allocations, CL 12/SL 10/EL 15, computed on the fly like
  Attendance's percentage, no persisted balance entity), BR-HR-005
  (statutory deductions — `deductions_json` is caller-supplied, matching
  Fees' GST-gap precedent), BR-HR-006 (Full & Final Settlement — out of
  scope, no `Settlement`/`ExitRecord` entity anywhere in Appendix-G,
  matching Fees' `CreditNote` deferral), and payslip generation (data-only
  via `PayrollRun`'s own fields, no `Document`/PDF, matching Examination's
  report-card precedent). `docs/design/School-ERP-Module-Architecture.md`'s
  HR & Payroll row updated to Designed.
- **Closed two of the three seams ADR-006 left open**: `TimetableEntry.
  employee_id` now gets a real existence check via the new
  `EmployeeService::getEmployee()` (ADR-006 §1, ADR-008 §2) —
  `TimetableEntryService` updated in this same pass, a small targeted
  change to already-shipped Stage 6b code. `StaffAttendanceRecord`
  (`ENT-ATT-002`) is built for real, inside the Attendance module (per
  Appendix-G's own `Module: ATT` designation, not HR & Payroll) — a joint
  addition across two modules in one stage, the same shape ADR-006 itself
  used for Timetable+Attendance (ADR-006 §2, ADR-008 §3).
- **Correction to this roadmap's own prior text**: the third seam this
  section previously listed, `PromotionRecord.fee_closure_confirmed`
  (ADR-005 §3), is a **Fees** seam, not an HR & Payroll one — Fees already
  exists (Stage 6c) and could close it, but that's out of this stage's
  scope and wasn't done here. Left open, correctly attributed, for a
  small dedicated follow-up rather than silently expanded into this
  already-substantial stage.
- Migrations: `departments`, `designations`, `employees`, `payroll_runs`,
  `leave_requests`, `attendance_closures` (additive, HR-owned),
  `staff_attendance_records` (Attendance-owned) — all seven applied.
- Verification: 12 new PHPUnit tests (122 total) — BR-HR-001's gate
  (blocked without closure, succeeds once Attendance pushes one, correct
  `net_pay` computation), BR-HR-002 (exit revokes the linked User's
  access in the same transaction), BR-HR-003 (duplicate run rejected),
  BR-HR-004 (over-balance approval blocked, succeeds with a logged
  override), BR-HR-007 (a `Processed` run can't be processed again), and
  the Attendance-side reconcile → close-period → closure-record
  integration test. Also manually smoke-tested the full flow end-to-end
  against the real dev server: department/designation/employee create →
  payroll run blocked pre-closure → staff attendance record → reconcile
  → close-period → payroll run succeeds with correct net pay → leave
  request create/approve → employee exit deactivates the linked user
  account — all verified over real HTTP.

## Stage 6e — Library and Transport implementation — DONE (2026-08-07)

Reference: `docs/design/library/Phase-1` through `Phase-3` and
`docs/design/transport/Phase-1` through `Phase-3`, per
`docs/ADR/ADR-009-library-and-transport-scope-decisions.md`. Bundled in
one pass — both are small, and both checked out clean against
Appendix-G's FK graph (Stage 6b's lesson, checked again): `BookIssue.
borrower_ref_id` validates against SIS's `Student` or HR & Payroll's
`Employee` (both now exist — the first module needing zero undesigned-
dependency stubs), `TransportAllocation.student_id` against SIS's
`Student`.

- **Design pass first**: ADR-009 resolves BR-LIB-001 (max books per
  borrower — decided 3, ADR-009 §2), BR-LIB-002 (overdue fine — decided
  ₹2/day, computed and stored on `BookIssue` but not posted to Fees, no
  ad-hoc-charge capability exists there), BR-LIB-003 (lost-book
  replacement — decided ₹500 flat, since `Book` has no `price` field to
  compute a variable charge from), BR-LIB-005 (outstanding-fine block —
  decided ₹0 threshold), BR-LIB-006 (Reservation queue — out of scope, no
  `Reservation` entity in Appendix-G), BR-TRN-003/005/006 (GPS live
  tracking, route-change fee recalculation, driver/trip validity — all
  out of scope, needing integrations or entities that don't exist).
  `docs/design/School-ERP-Module-Architecture.md`'s Library and Transport
  rows updated to Designed.
- **Two structural gaps closed with decided additive columns, not
  invented entities**: `BookIssue.status`/`replacement_charge_amount`/
  `fine_settled` (ADR-009 §1, §4, §6 — `Book` itself has no state beyond
  `is_available`, despite its own Lifecycle line naming a Lost/Damaged
  state) and `Route.vehicle_id` (ADR-009 §8 — Appendix-G's Relationships
  section describes a Route↔Vehicle link that no attribute catalogue
  column actually carries) — same category of gap-closing addition as
  Academic's `locked_by_closed_exam` (ADR-005 §10) and HR & Payroll's
  `attendance_closures` (ADR-008 §4), just an FK instead of a flag this
  time.
- **BR-TRN-001's capacity ceiling got a genuine concurrency-safe
  implementation**: `TransportAllocationService::allocate` locks the
  target `Route` row (`SELECT ... FOR UPDATE`) before counting active
  allocations and comparing against capacity, inside one transaction with
  the insert — the same row-lock shape `SeatAllocationModel::
  incrementSeatsFilled` established in Stage 4 for Admission, the first
  reuse of that specific pattern.
- **Real bug found and fixed during this pass, not specific to
  Transport**: `RouteModel::findForUpdate()`'s raw `SELECT ... FOR
  UPDATE` query originally constructed the returned Entity via
  `new Route($row)` — passing a raw DB row (with an already-JSON-encoded
  `stops_json` string) straight into the constructor double-encodes it,
  because `fill()`/`__set()` also runs the value through the field's
  "set" cast (`json_encode`), not just "get". `Model::find()` doesn't hit
  this because CI4 hydrates query results differently internally. Fixed
  by using `Entity::injectRawData()` instead of the constructor for
  hand-rolled raw-query hydration — stores the row as-is, matching how
  `Model::find()` behaves. Worth remembering for any future raw-SQL row
  lock: **never pass a raw DB row into an Entity constructor when a
  field has a "set" transform (JSON, encrypted, etc.) — use
  `injectRawData()`.**
- Migrations: `books`, `book_issues`, `vehicles`, `routes`,
  `transport_allocations` — all five applied.
- Verification: 14 new PHPUnit tests (136 total) — BR-LIB-001/004/005
  (limit, Reference-block, outstanding-fine block), BR-LIB-002 (fine
  computed against a real 5-days-overdue fixture, exact amount asserted,
  not just presence), BR-LIB-003 (flat replacement charge), BR-TRN-001
  (route-vs-vehicle capacity check, and the row-lock-guarded allocation
  ceiling itself), BR-TRN-002 (single active route per student), and
  BR-TRN-004 (emergency-contact format validation). Also manually
  smoke-tested the full flow end-to-end against the real dev server: book
  create → issue → Reference-book rejection → overdue return with a
  correctly computed fine → vehicle create → route-capacity-exceeds-
  vehicle rejection → route create → student transport allocation, all
  verified over real HTTP.

## Stage 6f — Communication and Reports implementation — DONE (2026-08-07)

Reference: `docs/design/communication/Phase-1` through `Phase-3` and
`docs/design/reports/Phase-1`, per
`docs/ADR/ADR-010-communication-and-reports-scope-decisions.md`. Bundled
in one pass — Reports has no domain model of its own (its whole shape is
"what can every already-shipped module's Service layer already answer"),
so there was never an independent Reports design to sequence separately.
`Circular.author_id → User` and `NotificationLog.recipient_ref_id`
(polymorphic against `Guardian`/`Employee`/`User`) all checked clean
against Appendix-G's FK graph — the second module in a row (after
Library/Transport, ADR-009) needing zero undesigned-dependency stubs.

- **Design pass first**: ADR-010 resolves BR-COM-004 (delivery-failure
  logging — implemented for real, `failure_reason` required and logged
  via `AuditLog::ACTION_OVERRIDE`) and explicitly scopes out BR-COM-001
  (direct teacher-parent messaging — no `Message` entity anywhere in
  Appendix-G), BR-COM-002/003 (bulk-send authorization, emergency
  override — both govern an actual SMS/Email/Push gateway dispatch that
  doesn't exist, vendor explicitly "Client/Product Decision Required"),
  and BR-COM-005 (retention — already satisfied by this codebase's
  existing indefinite-soft-delete baseline, nothing new to build).
  `docs/design/School-ERP-Module-Architecture.md`'s Communication and
  Reports rows updated to Designed.
- **`NotificationLog` is a log/record-keeping entity, not a live
  dispatcher** — `NotificationLogService` offers `create`/
  `markDispatched`/`markDelivered`/`markFailed`, but nothing calls out to
  a real SMS/Email/Push gateway. This is deliberate, matching Library's
  "compute and store, don't post externally" precedent (ADR-009 §3/§4)
  for the same reason: no external integration exists to call.
- **Closed the logging half of two seams ADR-006 left open**:
  `TimetableEntryService::reviseEntry` (BR-TT-005) and
  `AttendanceService::markAttendance` when `state = ABSENT` (BR-ATT-004,
  notifying the student's primary guardian, resolved via SIS's
  `StudentGuardianLinkService`) now call `NotificationLogService::
  create()` — small, targeted changes to already-shipped Stage 6b code.
  The *delivery* half of both seams stays open, now correctly attributed
  to "no gateway integration" rather than "no notification-log entity."
- **Reports is deliberately the smallest honest slice of FR-40**: a
  single `GET /api/v1/reports/summary` endpoint, composed entirely from
  ten already-existing list-all Service methods across nine other
  modules (Administration, Academic, HR & Payroll, Library, Transport,
  Fees) — no new query method was added to any other module to support
  it. Role-scoped dashboards, the custom report builder, trend
  analytics, and Excel/PDF export (FR-40/41/42, BR-RPT-001/002/003/004)
  are explicitly out of scope — no Excel/PDF library exists in this
  codebase, no field-level authorization config entity exists, and
  building genuine cross-module aggregates (fee collection totals,
  school-wide attendance percentages) would need new methods added to
  five already-shipped modules' Services speculatively, which ADR-010 §8
  refuses for the same reason ADR-009 §13 already refused a unilateral
  Fees change from Transport's side.
- Migrations: `circulars`, `notification_logs` — both applied.
- Verification: 10 new PHPUnit tests (146 total) — Circular create/
  retract/re-retract-rejected/list-by-audience, `NotificationLog`
  create/dispatch/deliver, BR-COM-004's mark-failed-with-reason, and the
  headline integration tests proving the two closed seams for real: a
  timetable revision logs a `Queued` notification to the assigned
  teacher, marking a student `ABSENT` logs one to their primary guardian
  (resolved through a real `StudentGuardianLink` fixture, not asserted
  against a stub), and marking `PRESENT` logs nothing. Also manually
  smoke-tested end-to-end against the real dev server: reports summary →
  circular create/retract → notification log create → BR-COM-004's
  reason-required validation → mark failed, all verified over real HTTP.

Every module Appendix-G's Data Dictionary defines is now designed and
implemented. Stage 6 is complete.

## Stage 7 — Configuration entity — DONE (2026-08-07)

Reference: `docs/design/administration/Phase-7-Configuration-Design.md`,
per `docs/ADR/ADR-011-configuration-entity-scope-decisions.md`. The
first Stage 7 follow-up item, picked first because it's the one every
other follow-up item's own ADR explicitly named as blocking it
("pending a future Configuration entity").

- **Design pass first**: ADR-011 cross-checked Appendix-C §3.5's full
  24-item Consolidated Configurable Items list against what Stage 6
  actually built, and found only **10 items** exist as real, working
  decided-default constants in already-shipped Service code — the other
  14 were never implemented as a scalar constant at all (either the
  surrounding feature is itself out of scope in an already-accepted ADR,
  or the item is a policy/list/role-name, not a single value
  `Configuration`'s `setting_value VARCHAR(500)` shape can naturally
  hold). Only the 10 real ones migrate, plus HR & Payroll's leave
  allocations (CL/SL/EL, three keys) folded in from ADR-008 §7 — twelve
  `configurations` rows total, seeded directly in the creation migration
  per Appendix-G's own Lifecycle line ("Created at implementation"), not
  via a runtime `POST` (no such endpoint exists).
  `docs/design/School-ERP-Module-Architecture.md`'s Administration row
  gains `Configuration` as designed.
- **Six already-shipped Services refactored in the same pass** —
  `BookIssueService`, `TimetableEntryService`, `AttendanceService`,
  `MarksRecordService`, `InvoiceService`, `LeaveRequestService` — each
  had its private decided-default constant removed and replaced with a
  call to `ConfigurationService::getNumber()`. `Config\Services.php`'s
  six corresponding factory methods now inject `static::
  configurationService()`. `AttendanceService::correctAttendance`'s
  same-day boolean check was reworked into a numeric day-window
  comparison against `attendance.edit_window_days` (default 0 = same day
  only) — same semantic, now genuinely parametrized rather than
  hardcoded to "today."
- Migration: `configurations` — applied, with its twelve-row seed.
- Verification: 5 new PHPUnit tests (151 total) — seeded-value read,
  update, `is_editable = false` rejection, list-by-module, and the
  headline test proving a refactored Service's enforcement is genuinely
  config-driven (not still secretly hardcoded): changing
  `library.max_books_per_borrower` to 1 via the API and confirming
  `BookIssueService` immediately enforces the new ceiling. All 151 prior
  tests still pass unmodified — the seeded defaults match the removed
  constants exactly, so no existing assertion needed to change. Also
  manually smoke-tested end-to-end against the real dev server: read a
  seeded key → list by module → update → confirm the change persisted →
  reverted back to the original seeded value.

## Stage 8 — Document/PDF module — DONE (2026-08-07)

Reference: `docs/design/administration/Phase-8-Document-Design.md`, per
`docs/ADR/ADR-012-document-pdf-module-scope-decisions.md`. Closes three
gaps three separate prior ADRs each named from a different angle:
Examination's report-card PDF (ADR-005 §9), HR & Payroll's payslip PDF
(ADR-008 §10), and — as a bonus, since `Document.owner_type` already
names it in Appendix-G — Fees' invoice PDF (FR-23).

- **Design pass first**: ADR-012 cross-checked all three Appendix-E
  requirements that name document generation (FR-09 ID Card/Certificate,
  FR-20 Report Card, FR-23 Invoice/Receipt) and took up only two of the
  three for real — FR-20 and FR-23 render entirely from data this
  codebase already computes; FR-09 needs a school-branded template and
  student-photo capability that don't exist anywhere, and is itself
  marked "Desirable, not Mandatory" in RGD v2.0, so it stays deferred.
  `dompdf/dompdf` (pure PHP, no native extensions, Hostinger-compatible)
  is this codebase's first PDF-rendering dependency.
  `docs/design/School-ERP-Module-Architecture.md`'s Administration row
  gains `Document` (`ENT-SYS-006`) as designed.
- **`Document`** — a generic, polymorphic file-metadata entity
  (`owner_type`: `Application`/`Student`/`Invoice`/`ReportCard`/
  `PayrollRun` — the last one added beyond Appendix-G's literal list
  since payslips needed an owner type too) — stores files under
  `writable/uploads/documents/`, local disk decided over cloud (Appendix-
  G's own "Client/Product Decision Required," resolved using this
  project's existing writable-storage convention, no new vendor
  account needed).
- **Three Services each gained one PDF-generation method** —
  `ReportCardService::generatePdf`, `PayrollRunService::
  generatePayslipPdf` (rejects unless `status = Processed`, matching
  BR-HR-007's immutability), `InvoiceService::generateInvoicePdf`
  (doubles as the "receipt" once paid — no separate receipt template).
  All three render a plain, functional HTML table through the new
  `App\Core\Pdf\PdfRenderer` helper — no school branding asset exists to
  include, same reasoning FR-09 stays deferred.
- **Real bug caught during this pass**: the first draft of
  `ReportCardService::generatePdf` called `$exam->examName`
  (`ExamResponse`'s camelCase property) against the raw `Exam` *entity*
  `requireExam()` actually returns (snake_case `exam_name`) — a
  `TypeError` at generation time, caught by the feature test, not
  shipped. Worth remembering: **a Service's own private
  `requireX()`/`requireY()` helpers return raw Entities, not Response
  DTOs — camelCase property access only works on the DTO, never the
  Entity it wraps.**
- Migration: `documents` — applied, no seed data (created only on
  generation, per its own Lifecycle line).
- Verification: 6 new PHPUnit tests (157 total) — report-card PDF
  generation + download (asserting the downloaded file is a real,
  non-empty PDF, not just a 200 status), payslip generation blocked
  pre-`Processed` and succeeding once `Processed`, invoice PDF
  generation, `Document` list-by-owner, and `DOCUMENT_NOT_FOUND` for a
  missing id. Also manually smoke-tested end-to-end against the real dev
  server: generated and downloaded a real invoice PDF (verified valid
  PDF structure, non-trivial size) and a report card PDF against actual
  leftover dev data.

## Stage 9 — Timetable Substitution (BR-TT-004/FR-16) — DONE (2026-08-07)

Reference: `docs/design/timetable/Phase-4-Substitution-Design.md`, per
`docs/ADR/ADR-013-timetable-substitution-scope-decisions.md`. Closes the
follow-up ADR-008 §11 flagged when `StaffAttendanceRecord` shipped.

- **Design pass first**: ADR-013 resolved the one genuinely open product
  decision — Appendix-E's flagged "no fallback policy defined if no
  eligible substitute exists." Decided: always create a `Substitution`
  row, `status = UNSUPERVISED` with `substitute_employee_id = NULL` when
  no candidate is available, never reject the request outright (FR-16's
  own alt flow already names this outcome). An explicitly-supplied but
  genuinely ineligible/unavailable substitute is still a hard reject —
  that is caller error, not the undefined-fallback case.
- **`SubjectTeacherEligibility`** — net-new entity (no Appendix-G card
  names it), a minimal `employee_id`/`subject_id` pair, admin-managed
  via create + list-by-subject only, no update/delete (ADR-009 §13
  precedent against speculative CRUD).
- **`Substitution`** — one row per `timetable_entry_id` +
  `substitution_date`. Applies "for that date only" — creating one never
  touches `timetable_entries` (no `UPDATE`, no `version_no` bump),
  keeping it structurally separate from BR-TT-005's `reviseEntry()`.
- **`StaffAttendanceService::wasAbsentOn()`** — new method, the
  one-way Timetable → Attendance call gating substitution creation
  (`Unauthorized`/`On Leave` that date, or `TEACHER_NOT_ABSENT`).
- **Notification**: reuses `NotificationLogService::create()` exactly as
  BR-TT-005 already does — log-only, no live dispatch (ADR-010 §3).
  `NotificationLogService` validates `recipient_ref_id` against a real
  entity, and Timetable has no guardian-enumeration dependency to
  produce a real `Guardian` id for a section's students (a first-draft
  section-level log against `recipient_type = Guardian` failed exactly
  this validation in testing) — logs against the real Employee on the
  other side of the transaction instead (substitute if `ASSIGNED`,
  absent teacher if `UNSUPERVISED`).
- New endpoints (base `/api/v1/timetable`): `POST`/`GET
  subject-teacher-eligibilities`, `POST substitutions`, `GET
  substitutions/{id}`, `GET entries/{id}/eligible-substitutes`.
- Migrations: `subject_teacher_eligibilities`, `substitutions` — applied.
- Verification: 6 new PHPUnit tests (163 total) — eligible-substitute
  auto-assignment (asserting the actual computed substitute id, not just
  status), explicit-substitute assignment, the UNSUPERVISED fallback
  when no candidate exists, rejection when the absent teacher's
  `StaffAttendanceRecord` doesn't actually show them absent that date,
  rejection of an explicitly-supplied ineligible substitute, and the
  eligible-substitutes review endpoint. Also asserts the originating
  `TimetableEntry` row is untouched (`version_no` still 1,
  `employee_id` unchanged) after a substitution is created — the
  date-scoped/not-version-bumped distinction ADR-013 §3 makes
  structural.

## Stage 10 — Fees/Transport route-tier linkage + Examination's fee_closure_confirmed — DONE (2026-08-07)

Reference: `docs/ADR/ADR-014-fees-transport-promotion-seams.md`. Closes
two named cross-module seams left open only because their dependency
didn't exist yet at design time — same "unblocking pass" shape as
Stage 9 (Substitution) — bundled together per this project's precedent
of grouping small related follow-up items (Stage 6e Library+Transport,
Stage 6f Communication+Reports).

- **BR-FEE-003/BR-TRN-005 (route-based Transport fee-tier)** — additive
  nullable `FeeStructure.route_id` (same additive-column shape as
  Academic's `locked_by_closed_exam`), unique key extended to
  `(class_id, fee_head_id, academic_session_id, category, route_id)` so
  a route-tier row coexists with the base row. `InvoiceService::
  generateInvoice()` now folds in the student's active
  `TransportAllocation.route_id` automatically (Fees reading Transport,
  same shape as its existing SIS/Academic calls). `TransportAllocationService`
  gains `changeRoute()` (BR-TRN-005 had no route-change mutator before
  this) which, after committing the route change, explicitly triggers
  the new `InvoiceService::recalculateForRouteChange()` — Transport
  pushing the fact into Fees, never the reverse; only recalculable
  (`UNPAID`, unlocked) invoices are touched.
- **`PromotionRecord.fee_closure_confirmed` (BR-SIS-001)** — now fully
  system-computed via `InvoiceModel::
  existsOutstandingByStudentIdAndSession()` (outstanding =
  `UNPAID`/`PARTIALLY_PAID`/`DEFAULTER` for the `from_session_id` being
  closed out of), the same treatment `academic_closure_confirmed`
  already got in ADR-005 §3. `CreatePromotionRecordRequest.
  feeClosureConfirmed` is removed entirely (a breaking API change to
  `POST /examination/promotions`) rather than kept-but-ignored.
- Migration: `2026-08-07-210001_AddRouteIdToFeeStructuresTable.php` —
  applied.
- Verification: 5 new PHPUnit tests (162 total) — automatic route-tier
  fee inclusion at invoice generation (and its negative case: a
  route-tier row for a route the student isn't on is excluded),
  `changeRoute()`'s recalculation trigger asserting a real recomputed
  `total_amount` before/after the route change, and `promoteStudent()`
  now computing `fee_closure_confirmed` from real `Invoice` data (a
  blocked case with an outstanding invoice, a succeeding case with a
  `PAID` one). Rebased onto Stage 9's 163-test baseline, bringing the
  total to 168 (163 + 5 new).

## Stage 11 — BR-HR-004 override authority: first RBAC enforcement — DONE (2026-08-07)

Reference: `docs/ADR/ADR-015-hr-payroll-rbac-enforcement.md`. Closes the
one item ADR-011 §5 flagged as genuinely enforceable-not-just-configurable
in its fourteen-item survey: BR-HR-004's override authority was decided
("HR role, logged," ADR-008 §7) but never checked — any authenticated
caller could supply `override_reason` to push a leave balance negative.
This is the first place in the codebase that actually reads
`RequestContext::permissionSet()` to gate an action, not just attribute
one (`RequestContext::userId()` was already used everywhere for audit
attribution, but no permission check existed anywhere before this).

- New `LeaveRequestService::PERMISSION_OVERRIDE`
  (`'hr_payroll.leave.override'`) — a permission string, not a
  hardcoded role name, matching `Role.permission_set`'s existing
  JSON-array-of-strings design (no seeded/default role names exist
  anywhere in this codebase — every role is admin-created data).
  `decide()` now throws `AuthorizationException`
  (`OVERRIDE_NOT_PERMITTED`, 403) when a negative-balance approval is
  attempted with `override_reason` but the caller's JWT-decoded
  `permission_set` lacks the string — distinct from the existing 422
  `INSUFFICIENT_LEAVE_BALANCE` (no override attempted at all).
- Deliberately narrow: no broader RBAC sweep across other Controllers —
  every other endpoint still relies on authentication alone, matching
  this project's repeated scope discipline against speculative
  additions (ADR-009 §13, ADR-010 precedent). This ADR enforces exactly
  the one decision that was already made and documented, not a general
  authorization pass.
- Verification: existing override-success test updated to explicitly
  grant the new permission (default test role's
  `['read','create','update','delete']` set doesn't include it); one
  new test asserts the 403 for a caller without it. 169 passing tests
  total (1 new test; the existing override test also changed shape but
  wasn't new).

## Ongoing, every stage

- Git: feature branches (Company Development Standard §6), PR review before
  merge, squash-merge.
- Commits: Conventional Commits format (§7).
- CI gate: lint + static analysis + automated tests, before any merge.
- Code Review Checklist (§11) and, once a stage reaches a releasable state,
  the Release Checklist (§12).
- **API docs**: every new endpoint gets an OpenAPI attribute
  (`#[OA\Get]`/`#[OA\Post]`/etc.) directly on its Controller method — see
  `app/Modules/Administration/Controllers/*` for the pattern established in
  Stage 1, and `app/Core/OpenApi/Spec.php` for shared response/error
  schemas. `composer openapi` regenerates `public/openapi.json` (gitignored
  — a build artifact, never hand-edited or committed, per §5); the UI is at
  `/docs/` (static page, loads the spec from `/openapi.json`).

## Immediate next action

Stages 0 through 11 are done (2026-08-07) — every module in Appendix-G's
Data Dictionary is real, working, tested code, plus a real
`Configuration` entity, a real `Document`/PDF-generation capability,
Timetable Substitution (BR-TT-004/FR-16), the Fees/Transport/
Examination cross-module seams, and BR-HR-004's RBAC enforcement closed
(169 passing tests). Remaining work is follow-up/deepening, not
new-module design:

- A real SMS/Email/Push gateway integration once a vendor is chosen
  (ADR-010 §1/§2/§5) — unblocks BR-COM-002/003 and live delivery for the
  two notification seams Stage 6f closed only the logging half of.
- A genuine Reports dashboard pass, once real requirements are scoped —
  adding aggregate query methods to the *owning* source modules (ADR-010
  §8), not retrofitting them speculatively. Can now reuse Stage 8's
  `dompdf`/`DocumentService` for Excel/PDF export once scoped.
- FR-09 ID Card/Certificate generation (SIS) — needs a real branding
  template and student-photo capability, explicitly deferred by
  ADR-012 §4.
- The remaining thirteen Appendix-C §3.5 configurable items ADR-011 §5
  explicitly did not migrate (BR-TT-004's own entry now resolved for
  real by ADR-013, not by this list — see ADR-011's corrected note) —
  each needs its underlying feature built first (a real entity,
  workflow, or integration: GPS ingestion, driver/vehicle/trip entities,
  GST line-items, RTE waived-fee-head list, PF/ESI/PT slabs, seat-hold
  policy, etc.), not just a `Configuration` row with nothing to plug
  into yet. None of these are small — every one is a real feature build,
  not a config tweak.

None of these block anything else — check with the project owner on
priority before starting any of them.
