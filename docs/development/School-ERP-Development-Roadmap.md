---
status: Active
last-updated: 2026-08-06
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

## Stage 6c onward — remaining undesigned modules

Not yet designed: Fees, Library, Transport, HR & Payroll, Communication,
Reports. Suggested order, by dependency:

1. **Fees** — depends on Academic's `AcademicSession`/`Class`. Its design
   should account for `PromotionRecord.fee_closure_confirmed`'s seam
   (ADR-005 §3) — moving it from caller-supplied to system-computed.
2. **HR & Payroll** — closes two seams: `TimetableEntry.employee_id`
   validation (ADR-006 §1) and `StaffAttendanceRecord` (ADR-006 §2, likely
   a joint pass with Attendance revisited). Also unlocks BR-TT-004/FR-16
   Substitution, which needs staff-absence data.
3. **Library**, **Transport**, **Communication**, **Reports** — lowest
   inter-dependency; freely reprioritizable by business value.
   Communication also closes two seams: BR-TT-005's revision notification
   (ADR-006 §6) and BR-ATT-004's absence notification (ADR-006 §9).

Each follows the same design-then-implement pattern Academic/Admission/
SIS/Examination/Timetable/Attendance now demonstrate six times over —
those modules' design sets are the template, not just prior work to
reference. In particular: **before assuming a stage's dependency
ordering from the roadmap's own prose, check the actual FK graph in
Appendix-G** — Stage 6b's own "no inter-dependency" assumption was wrong,
caught only by reading the entity cards directly.

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

Stages 0 through 6b are done (2026-08-06) — Academic, Admission, SIS
(with Confirm Enrollment), Examination (ADR-005), and Timetable/Attendance
(ADR-006, including the real BR-ATT-006 ↔ Examination eligibility wiring)
are all real, working, tested code (96 passing tests). Next: **Stage 6c**
— Fees is the suggested next module (see the Stage 6c section above for
the seam it should account for). Run it through the same
Requirement → Design → Approval sequence the last two modules went
through (ADR-005/ADR-006 as templates), and check Appendix-G's actual FK
graph for the entity before assuming its dependency ordering — don't
trust this document's own prose blindly, per Stage 6b's own lesson.
