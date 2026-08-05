# Design Artifact Index

Authoritative, current-state design documentation. Each file reflects the latest approved revision — not a turn-by-turn change log. ADRs live in `docs/ADR/`.

**Status as of 2026-08-06: fully ready.** ADR-004 resolved every open item across Academic, Admission, and SIS. Nothing below is blocked.

## Cross-cutting references (start here)

- [School ERP — Module Architecture & Boundaries](School-ERP-Module-Architecture.md) — the current module list, namespace, and entity ownership; supersedes the archived MDB/BMDD module trees
- [School ERP — Database Supplement](School-ERP-Database-Supplement.md) — partitioning candidates, MySQL adaptations, the decided archival-trigger thresholds; supersedes the archived DDD
- [School ERP — API Governance Supplement](School-ERP-API-Governance-Supplement.md) — Appendix-K addendum, deprecation lifecycle, performance targets; supersedes the archived ASD
- `docs/COMPANY_DEVELOPMENT_STANDARD.md` — the company-wide baseline all three of the above sit on top of
- [`docs/development/School-ERP-Development-Roadmap.md`](../development/School-ERP-Development-Roadmap.md) — the implementation sequencing for everything below (Stage 0 bootstrap through Stage 6 remaining modules)

## ADRs
- [ADR-001](../ADR/ADR-001-academic-master-data-package.md) — Academic Master Data module ownership
- [ADR-002](../ADR/ADR-002-sis-guardian-ownership-and-br-sis-004-deferral.md) — SIS owns Guardian/StudentGuardianLink; BR-SIS-004 deferred
- [ADR-003](../ADR/ADR-003-student-stub-creation-ownership.md) — Admission owns Student stub-creation trigger; SIS owns entity/maintenance
- [ADR-004](../ADR/ADR-004-student-stub-creation-shape-and-section-id-timing.md) — Resolves `section_id` timing (DG-SIS-001), the concrete `createStudentStub` contract, the single-transaction Confirm Enrollment boundary, and the Confirm Enrollment operation identity

## Documentation Gaps
- [DG-SIS-001](sis/DG-SIS-001.md) — `Student.section_id` creation-time timing/mechanism. **Resolved 2026-08-06, see ADR-004.**

## Administration Module — minimal slice (`docs/design/administration/`) — fully ready
- [Phase 1 — Domain Model](administration/Phase-1-Domain-Model.md) — `User`, `Role`, `AuditLog`, plus supporting `refresh_tokens`
- [Phase 2 — Model (Repository) Design](administration/Phase-2-Model-Design.md)
- [Phase 3 — DTO Design](administration/Phase-3-DTO-Design.md)
- [Phase 4 — Service Design](administration/Phase-4-Service-Design.md) — `AuthService` (JWT/lockout), `UserService`, `RoleService`, `AuditService`
- [Phase 5 — Controller Design](administration/Phase-5-Controller-Design.md)
- [Phase 6 — Closure Report](administration/Phase-6-Closure-Report.md) — ready; this is the actual first module to implement (Development Roadmap Stage 1)

`Configuration`, `Document`, `ApprovalRequest` remain not-yet-designed — deliberately deferred, not an oversight.

## Academic Module (`docs/design/academic/`) — fully ready
- [Phase 1 — Domain Model](academic/Phase-1-Domain-Model.md)
- [Phase 2 — Model (Repository) Design](academic/Phase-2-Model-Design.md)
- [Phase 3 — DTO Design](academic/Phase-3-DTO-Design.md)
- [Phase 4 — Service Design](academic/Phase-4-Service-Design.md) — fully finalized, including the `GradingScheme` update/immutability decision
- [Phase 5 — Controller Design](academic/Phase-5-Controller-Design.md) — fully finalized
- [Phase 6 — Closure Report](academic/Phase-6-Closure-Report.md) — ready for implementation, no Open items

## Admission Module (`docs/design/admission/`) — fully ready
- [Phase 1 — Domain Model](admission/Phase-1-Domain-Model.md) — `Application`, `SeatAllocation`
- [Phase 2 — Model (Repository) Design](admission/Phase-2-Model-Design.md)
- [Phase 3 — DTO Design](admission/Phase-3-DTO-Design.md)
- [Phase 4 — Service Design](admission/Phase-4-Service-Design.md) (core CRUD; excludes Confirm Enrollment, see Phase 6) — includes the pessimistic-locking decision for `SeatAllocation` concurrency
- [Phase 5 — Controller Design](admission/Phase-5-Controller-Design.md) (core CRUD; excludes Confirm Enrollment, see Phase 6)
- [Phase 6 — Service Design: FR-02 Confirm Enrollment](admission/Phase-6-Service-Design-Confirm-Enrollment.md) (Revision 2 — fully finalized per ADR-004)
- [Phase 7 — Closure Report](admission/Phase-7-Closure-Report.md) — ready for implementation, no Open items

## SIS Module (`docs/design/sis/`) — fully ready
- [Phase 4.2 — Domain Model](sis/Phase-4.2-Domain-Model.md) (Revision 2)
- [Phase 4.3 — Repository Design](sis/Phase-4.3-Repository-Design.md)
- [Phase 4.4 — DTO Design](sis/Phase-4.4-DTO-Design.md) (Revision 2)
- [Phase 4.5 — Mapper Design](sis/Phase-4.5-Mapper-Design.md) (Revision 2 — fully finalized)
- [Phase 4.6 — Service Design](sis/Phase-4.6-Service-Design.md) (Revision 2 — fully finalized, including `createStudentStub`)
- [Phase 4.7 — Controller Design](sis/Phase-4.7-Controller-Design.md) (Revision 2 — fully finalized; no public create-student endpoint, by design)
- [Phase 4 Closure Report](sis/Phase-4-Closure-Report.md) (Revision 2)
- [Phase 5 — Implementation Plan](sis/Phase-5-Implementation-Plan.md) (Revision 2 — no gated milestones remain)
- [Phase 7 — Published SIS Service Interface Design](sis/Phase-7-Published-Service-Interface.md) (Revision 2 — concrete contract finalized)
- [Phase 11 — DG-SIS-001 Resolution Analysis](sis/Phase-11-DG-SIS-001-Resolution-Analysis.md) (Revision 1 — historical snapshot; concluded "still unresolved" as of Phase 11, superseded by ADR-004's later resolution)
- [Phase 12 — Requirement Clarification Package (DG-SIS-001)](sis/Phase-12-Requirement-Clarification-Package-DG-SIS-001.md) (Revision 1 — historical; superseded by ADR-004)
- [Phase 13 — Requirement Owner Decision Request (DG-SIS-001)](sis/Phase-13-Requirement-Owner-Decision-Request-DG-SIS-001.md) (**Decided 2026-08-06** — see ADR-004)

## Cross-Module (`docs/design/`) — fully ready
- [Phase 8 — Admission ↔ SIS Interaction Sequence Design](Phase-8-Admission-SIS-Interaction-Sequence.md) (Revision 2 — fully finalized)
- [Phase 9 — Architecture Readiness Review](Phase-9-Architecture-Readiness-Review.md) (Revision 2 — fully ready)
- [Phase 10 — Architecture Resolution Planning](Phase-10-Architecture-Resolution-Planning.md) (Revision 2 — roadmap executed, all items resolved)

## Not yet designed

Per [School-ERP-Module-Architecture.md](School-ERP-Module-Architecture.md): Attendance, Timetable, Examination, Fees, Library, Transport, HR & Payroll, Communication, Reports have an entity catalogue (Appendix-G) but no Phase-style design work yet. Administration's `Configuration`/`Document`/`ApprovalRequest` are in the same state (its `User`/`Role`/`AuditLog` slice is designed, see above). None are blocked on anything above — any of them can start independently whenever prioritized.

## Archived (2026-08-06 CI4 pivot cleanup)

The Spring Boot implementation was discontinued. Everything below was moved
to `docs/_archive/` because it was pure discontinued-codebase detail (build
logs, audit reports, PostgreSQL/Hibernate runtime setup) with no reusable
business or architectural content, or because it was a company-wide
standards document written entirely in Spring/Java terms and has since been
superseded by a CI4-native replacement:

- Sprint 1 — Enterprise Backend Foundation Plan, Sprint 1 — Backend
  Refactoring Plan, Sprint 1 Part 1 — Implementation Audit, Sprint 2 —
  Admission Module Audit (`docs/_archive/development/`)
- Runtime Verification and Testing Plan — PostgreSQL/Maven/Hibernate setup
  detail for the discontinued codebase (`docs/_archive/`)
- MDB Parts 1–4, BMDD Parts 1–4, DDD Parts 1–4, ASD Parts 1–4
  (`docs/_archive/MDB/`, `_archive/BMDD/`, `_archive/DDD/`, `_archive/ASD/`)
  — superseded by `docs/COMPANY_DEVELOPMENT_STANDARD.md` (company-wide) plus
  the three School ERP supplement documents linked at the top of this index
  (product-specific detail that didn't belong in the company-wide standard)

Everything else in this index was reviewed and, where it encoded a real
business/architectural decision expressed in Spring/Java-specific terms
(package layout, JPA, Bean Validation, `@RestController`, etc.), rewritten in
CodeIgniter 4/PHP terms rather than archived — the decisions themselves
carried forward unchanged; only the implementation vocabulary did not.

## Decisions made 2026-08-06 (ADR-004 and related)

The Requirement Owner delegated authority to resolve every remaining open
item rather than leave them pending. What was decided, and where:

- **`Student.section_id` is nullable at stub creation**, populated during FR-06 profile completion, BR-SIS-003-enforced at the `DRAFT → ACTIVE` transition — closes DG-SIS-001 (ADR-004 §1).
- **`full_name`/`dob` are copied from the confirmed `Application`** at stub-creation time — closes the previously-unaddressed nullability tension (ADR-004 §2).
- **Concrete SIS stub-creation contract**: `StudentService::createStudentStub`, internal-only, no public create endpoint on `StudentController` (ADR-004 §3).
- **`StudentSectionTransferRequest` serves both first assignment and later transfer** (ADR-004 §4).
- **Confirm Enrollment runs inside a single local database transaction** owned by Admission; no distributed transaction, no compensating-action pattern needed (ADR-004 §5).
- **Confirm Enrollment is the existing `Shortlisted`/`Waitlisted → Admitted` transition**, not a new operation; FR-02's "Approved" precondition refers to either of those statuses (ADR-004 §6).
- **`SeatAllocation`'s seat-count concurrency uses pessimistic row-level locking** (`docs/design/admission/Phase-4`).
- **`GradingScheme` has no versioning mechanism** — becomes immutable once referenced by a closed `Exam`; a "new version" is a new named scheme (`docs/design/academic/Phase-4`).
- **Data retention/archival thresholds** decided as a working default (3 years general, 7 years financial/Student-Guardian-post-exit) — explicitly flagged as a default needing eventual legal/compliance confirmation, not verified research (`School-ERP-Database-Supplement.md`).

## Open items

None remaining across Academic, Admission, or SIS.
