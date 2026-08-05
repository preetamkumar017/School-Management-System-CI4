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

## Stage 0 — Project bootstrap (infrastructure, no business logic)

- `git init` — this workspace has had no version control until now; real
  code needs it (see memory note: archive-not-delete was the workaround
  specifically because of this gap during the docs cleanup — it stops
  applying once history exists).
- Top-level structure: `backend/`, `database/`, `mobile/`, `docs/` (Company
  Development Standard §1).
- CI4 skeleton via `composer create-project codeigniter4/appstarter backend`.
- `.env` template (never committed with real values), `.gitignore`.
- Local MySQL 8 dev database.
- PHPStan + PHP_CodeSniffer (PSR-12 ruleset) wired as Composer scripts.
- PHPUnit scaffold.
- First commit.

## Stage 1 — Administration (minimal slice): design done, implementation next

Design complete (2026-08-06) — `docs/design/administration/Phase-1` through
`Phase-6`, covering `User`, `Role`, `AuditLog`, and a supporting
`refresh_tokens` table. `Configuration`, `Document`, and `ApprovalRequest`
remain deliberately deferred — nothing depends on them yet.

- Implement: migrations (`users`, `roles`, `audit_logs`, `refresh_tokens`),
  entities, models, `AuthService`/`UserService`/`RoleService`/`AuditService`,
  controllers.
- Delivers: an actual login/authenticate endpoint (JWT issuance, refresh,
  lockout), role assignment, and the audit-log write path every other
  module's Service layer needs.

## Stage 2 — Core infrastructure (`App\Core`)

Built against Stage 1's real tables, not stubs:

- `BaseEntity` / `BaseModel` — common audit columns, soft-delete global
  scope, `version` column support (Company Development Standard §4.4).
- `BaseController` — standard response envelope (§5).
- Auth library — JWT issuance/validation/refresh, wired to `users`.
- RBAC library — role/permission checks, wired to `roles`.
- Audit library — `AuditLog` writer, wired to `audit_logs`.
- Exceptions — the six fixed categories (§10) and their handlers.
- Logging — structured logger, request-id propagation (§8).
- **Verification gate:** a real end-to-end test — authenticate, hit a
  protected dummy endpoint, confirm an audit row is written — before any
  business module starts consuming Core.

## Stage 3 — Academic module implementation

Reference: `docs/design/academic/Phase-1` through `Phase-6` (fully
approved, no dependency beyond Core).

- Migrations: `academic_sessions`, `classes`, `sections`, `subjects`,
  `grading_schemes`, `class_subject_map`.
- Entities/Models/Services/Controllers per Phase 2–5.
- Verification: Phase 6's closure criteria — full CRUD test suite, including
  the `GradingScheme` immutability-once-referenced behavior.

## Stage 4 — Admission module implementation

Reference: `docs/design/admission/Phase-1` through `Phase-7` (fully
approved). Depends on Stage 3 (`Class`, `AcademicSession`).

- Migrations: `applications`, `seat_allocations`.
- Verification: a concurrency test proving the pessimistic row-lock on
  `SeatAllocation`'s counters actually prevents two simultaneous
  confirmations from both succeeding for the last open seat.

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

## Immediate next action

Stage 1's design is done (2026-08-06). Next: Stage 0 (bootstrap: git init,
CI4 skeleton) → Stage 1 implementation (Administration minimal slice) →
Stage 2 (Core) → Stage 3 (Academic — simplest fully-designed module, best
first real build).
