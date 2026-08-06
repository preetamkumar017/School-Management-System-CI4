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

## Stage 5 — SIS module implementation

Reference: `docs/design/sis/Phase-4.2` through `Phase-7`, `Phase-5`
Implementation Plan (fully approved). Depends on Stage 3 (`Section`) and
Stage 4 (`createStudentStub` is called from Admission).

- Migrations: `students`, `guardians`, `student_guardian_link`.
- Verification: the full Confirm Enrollment integration test ADR-004
  specifically requires — Admission calls `createStudentStub` inside its own
  transaction, and a forced SIS-side failure rolls back Admission's seat
  count and status change atomically (ADR-004 §5). This is the single most
  important test in the whole system so far — it's the one everything else
  was blocked behind before ADR-004.

## Stage 6 — Remaining modules (each needs its own design pass first)

Not yet designed: Attendance, Timetable, Examination, Fees, Library,
Transport, HR & Payroll, Communication, Reports. Suggested order, by
dependency and the deferred item each one closes:

1. **Examination** — closes ADR-002's deferred BR-SIS-004 (Historical
   Record Immutability); depends on Academic's `Subject`/`GradingScheme`.
2. **Attendance** + **Timetable** — depend on Academic's `Section`; no
   inter-dependency between the two.
3. **Fees** — depends on Academic's `AcademicSession`/`Class`.
4. **HR & Payroll**, **Library**, **Transport**, **Communication**,
   **Reports** — lowest inter-dependency; freely reprioritizable by business
   value once Stages 1–5 and Examination/Attendance/Timetable/Fees exist.

Each follows the same six-phase pattern Academic/Admission/SIS already
demonstrate — those three module's design sets are the template, not just
prior work to reference.

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

Stages 0, 1, 2, 3, and 4 are done (2026-08-06). Next: **Stage 5 — SIS
module implementation**, per `docs/design/sis/Phase-4.2` through
`Phase-7` — depends on Stage 3's `Section` and Stage 4's `Application`/
`SeatAllocation`. This stage also completes Admission's own FR-02 Confirm
Enrollment (`docs/design/admission/Phase-6`), deliberately deferred from
Stage 4: implement SIS's `StudentService::createStudentStub`, then
`AdmissionService`'s `ADMITTED` transition that calls it inside a single
shared transaction (ADR-004 §5), then the full Confirm Enrollment
integration test — the one that forces a SIS-side failure and proves
Admission's seat count/status change roll back atomically.
