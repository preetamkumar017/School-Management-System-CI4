---
status: Draft — for Backend Architecture Team review
date: 2026-08-05
audience: Backend Architecture Team, Sprint 1 delivery team
references: BMDD Parts 1-4, ASD Parts 1-2, ADR-001, ADR-002, ADR-003, DG-SIS-001
---

# Sprint 1 — Enterprise Backend Foundation Plan

## 1. Purpose and Scope

This document is the implementation roadmap for Sprint 1: the reusable Spring Boot backend
foundation that every one of the School ERP's business modules (Admission, Student
Information, Attendance, Examination, Fees, Library, Transport, HR & Payroll, Communication,
Reports, Academic) and the shared `administration` package will build on.

**This is not module implementation.** No Academic, Admission, SIS, Examination, Fee, HR,
Library, Transport, or Reports business logic is designed or built in this sprint. No database
table is designed. No REST API endpoint is designed. No source code is written in this
document — every item below is a design/build unit for the implementation team, stated as a
scope-and-standard reference, consistent with how BMDD Parts 1-4 and ASD Parts 1-2
themselves are written.

Baseline used: ADR-001, ADR-002, ADR-003, DG-SIS-001, Phase 4.2 Rev 1 through Phase 13 Rev 1
(SIS/Admission design chain), BMDD Parts 1-4, ASD Parts 1-2, and the Appendices those documents
cite by reference (Appendix-C, F, G, H, K, N).

## 2. Reusable Backend Components

For each component: **Purpose**, **Dependencies**, **Reusable by all modules**, and
**Blocked by ADR-003 / DG-SIS-001** (the two Open items named in scope).

### 2.1 Project Package Structure

| | |
|---|---|
| Purpose | Fix the top-level Java package tree the Spring Boot application is organized into, before any module code is written. |
| Dependencies | None — first structural decision of the sprint. |
| Reusable by all modules | Yes — every module's package sits under this tree. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

MDB Part-1 §6 (Backend Package Structure) already fixes this: alongside the 11 business-module
packages and the shared `administration` package (later extended to 12 modules by ADR-001's
`academic` package), MDB Part-1 §6 enumerates a `common/` package — with `config/`,
`security/`, `exception/`, and `util/` sub-packages — as the designated home for cross-cutting
technical infrastructure that is neither module business logic nor an `administration`-style
shared *business* service (AuditLogService, ConfigurationService, ApprovalRequestService,
DocumentService, NotificationService, UserService, RoleService). BMDD Part-1 §1 states it
consolidates the package structure "already fixed in Master Development Blueprint (MDB)
Part-1"; BMDD Part-1 §2-3's summary does not restate the `common` sub-package, but that is
BMDD's incompleteness in restating MDB, not a gap in the approved baseline itself, and per
BMDD Part-4 §39 a conflict between BMDD and an earlier, more foundational document (MDB) is
resolved in favor of the earlier document.

**Sprint 1 therefore adopts `common` as already-approved architecture, not a new proposal.**
No new ADR or governance ratification is required for the package's existence. Global exception
handling (2.4), the response wrapper (2.5), and the security foundation (2.7) map directly onto
MDB's named `exception/`, and `security/` sub-packages; Common Configuration (2.15) maps onto
`config/`; Utility Components (2.9) and the Reference Number Generation Framework (2.8) map onto
`util/`. Base Entity/Audit Support (2.3), Shared Enums (2.10), and Common Constants (2.11) are
not named as their own sub-packages in MDB Part-1 §6's four-item list; Sprint 1 places them under
`common` as a straightforward, non-contradictory elaboration of an already-approved package,
not as a new top-level architectural decision.

**Implementation status (2026-08-05): ✅ Implemented.** Realized via
Sprint-1-Backend-Refactoring-Plan.md REF-010 (root package `com.schoolerp`) and REF-012
(`config/`, `security/`, `exception/`, `util/`, `audit/`, `constant/`, `validation/`, `dto/`,
`mapper/`, `logging/` all nested under `common/`).

### 2.2 Folder Structure

| | |
|---|---|
| Purpose | Fix the physical repository layout (source roots, resource roots, migration location, test mirroring) that the package structure (2.1) sits inside. |
| Dependencies | 2.1 Package Structure. |
| Reusable by all modules | Yes. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

Standard single-module Spring Boot 3/Java 21 Maven or Gradle layout: `src/main/java` rooted at
the application's base package (containing the 12 business/administration packages plus the
proposed `common` package from 2.1); `src/main/resources` for `application.yml`/per-profile
config (2.14) and static resources; a dedicated schema-migration resource folder mirroring the
DDD's per-entity table ownership (DDD Parts 1-3); `src/test/java` mirroring `src/main/java`
package-for-package, per the Backend Testing Standards (BMDD Part-4 §33) requiring Unit Tests
per Service method and Integration Tests per API operation.

**Implementation status (2026-08-05): ✅ Implemented.** Standard Maven layout in place;
`src/test/java` mirrors `src/main/java` package-for-package for every class a test was added for.

### 2.3 Base Entity and Audit Support

| | |
|---|---|
| Purpose | Provide the shared base entity class (or equivalent) implementing the Common Columns baseline — surrogate ID, optimistic-lock version, audit columns (created/modified by-and-at), soft-delete flag — so every module's entity inherits it rather than repeating it (BMDD Part-2 §11). |
| Dependencies | 2.1 Package Structure (`common` placement), DDD Part-1 §7 Common Columns definition. |
| Reusable by all modules | Yes — every JPA entity in every module inherits this. |
| Blocked by ADR-003 / DG-SIS-001 | No — the base entity carries no `Student`-specific or SIS-specific field; ADR-003/DG-SIS-001 concern *what fields the `Student` entity itself carries and when*, not the shared base class every entity (including `Student`) will eventually extend. |

Also includes the shared audit-write contract that every state-changing Service method calls
(`AuditLogService`, owned by `administration` per BMDD Part-1 §6) — Sprint 1 fixes the base
entity's audit *columns*; the `AuditLogService` implementation itself is `administration`
package work, out of scope here unless the team elects to build it as part of foundation
hardening (see §3).

**Implementation status (2026-08-05): ✅ Implemented.** `common/base/BaseEntity.java`
(surrogate ID, `@Version`), `common/base/BaseAuditEntity.java` (audit columns +
`deleted`/`deletedAt` soft-delete, completed by Sprint-1-Backend-Refactoring-Plan.md REF-030),
`common/base/BaseDTO.java` (marker superclass for DTOs), `common/audit/JpaAuditingConfig.java`
(`@EnableJpaAuditing`, `AuditorAware` — intentionally a labeled placeholder returning `"system"`
until Security Foundation (2.7) exists to resolve a real principal). Tested by
`BaseAuditEntityTest`. The `AuditLogService` business service itself remains `administration`
package work, not started.

### 2.4 Global Exception Handling

| | |
|---|---|
| Purpose | Implement the single centralized exception-handling component serving every module's Controllers, realizing the five exception categories fixed in BMDD Part-2 §16: Validation, Business Rule, Integration, Concurrency, Unhandled/System. |
| Dependencies | 2.1 Package Structure, 2.5 Standard API Response Wrapper (exceptions render through it). |
| Reusable by all modules | Yes — BMDD Part-2 §16 explicitly forbids a module implementing its own separate exception-handling logic. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ✅ Implemented — all five categories now covered.**
`common/exception/GlobalExceptionHandler.java` is the single `@RestControllerAdvice` serving
every module. Validation (`ValidationException`, `MethodArgumentNotValidException`), Concurrency
(`ObjectOptimisticLockingFailureException`), and Unhandled/System (catch-all `Exception`) were
already implemented; **Business Rule and Integration — the two previously-missing categories
(Sprint-1-Backend-Refactoring-Plan.md REF-031) — were added in Sprint 1 Part 3**:
`BusinessRuleException` (carries an Appendix-C v1.1 rule ID, maps to HTTP 422) and
`IntegrationException` (maps to HTTP 502), both extending the existing `BaseRuntimeException`.
Tested by `GlobalExceptionHandlerTest`.

### 2.5 Standard API Response Wrapper

| | |
|---|---|
| Purpose | Implement the response envelope fixed in ASD Part-1 §10: a status indicator distinguishing success / validation failure / business-rule failure / authorization failure / system error, pagination metadata for list responses, and the DTO-only response-body rule (never a JPA entity). |
| Dependencies | 2.1 Package Structure. |
| Reusable by all modules | Yes — one wrapper type, every Controller returns through it (BMDD Part-1 §14). |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ✅ Implemented.** `common/response/ApiResponse.java`
(status/message/data/errorCode/meta, `PageMetadata` for list responses) already existed; **Sprint
1 Part 3 completed it** by adding a `requestId` field stamped from the current request's
correlation ID (`common.logging.MDCUtil`) on every response, success or error, per ASD Part-1 §9
/ ASD Part-2 §19 traceability (this also resolves Sprint-1-Backend-Refactoring-Plan.md REF-042).
Tested by `ApiResponseTest`.

### 2.6 Validation Framework

| | |
|---|---|
| Purpose | Establish the Bean Validation convention on Request DTOs (BMDD Part-1 §7) and the Controller-boundary short-circuit behavior (BMDD Part-2 §15) — a validation failure never reaches the Service layer. Also fixes where cross-field validation not expressible as a Bean Validation constraint is implemented (an explicit Service-method step, distinct from Business Rule enforcement). |
| Dependencies | 2.4 Global Exception Handling (Validation Exception category), 2.5 Response Wrapper (Validation Error response category). |
| Reusable by all modules | Yes — every module's Request DTOs use this same mechanism. |
| Blocked by ADR-003 / DG-SIS-001 | No — the *framework* (mechanism) is unblocked; a specific module's DTO shape (e.g., `CreateStudentRequest`, explicitly flagged by ADR-003 as requiring reconsideration) is module design, out of scope for Sprint 1 either way. |

**Implementation status (2026-08-05): ✅ Implemented.** Bean Validation is wired at the
Controller boundary (`spring-boot-starter-validation` on the classpath;
`GlobalExceptionHandler.handleMethodArgumentNotValid` short-circuits to the Validation Error
response category). **Sprint 1 Part 3 added `common/validation/ValidationUtils.java`** —
reusable assertion helpers (`requireTrue`, `requireNonNull`, `requireNonBlank`, `requireBefore`
for date-range checks) for the explicit, named cross-field validation step BMDD Part-2 §15 fixes
at the start of a Service method, distinct from Business Rule enforcement — every helper throws
the existing `ValidationException` so it renders through the same centralized handler path.
Tested by `ValidationUtilsTest`. Per-module Request DTO field-level constraints themselves are
module work, not started.

### 2.7 Security Foundation

| | |
|---|---|
| Purpose | Stand up Spring Security + JWT authentication, the RBAC authorization scaffold keyed to the Role Permission model, refresh-token lifecycle (Redis-backed), CORS/CSRF configuration, and the API-boundary rate-limiting hook — the mechanism fixed in MDB Part-2 §11-14, 19 and ASD Part-2 §11-18. |
| Dependencies | 2.1 Package Structure, 2.4 Global Exception Handling (Authentication/Authorization Failure categories), 2.5 Response Wrapper. |
| Reusable by all modules | Yes — every Controller method's authorization annotation (BMDD Part-1 §14) sits on top of this foundation. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

Note: specific numeric rate-limit thresholds remain Client/Product Decision Required per
Appendix-F NFR-APIP-001 (ASD Part-2 §17) — this is a pre-existing open item independent of
ADR-003/DG-SIS-001, tracked separately and not a Sprint 1 blocker (the enforcement mechanism
can be built with a placeholder, reviewable threshold).

**Implementation status (2026-08-05): ⏳ Not implemented — deliberately deferred.**
`common/security/SecurityConfig.java` exists as an interim, explicitly-labeled placeholder
(`permitAll()` on every request, stateless session, CSRF disabled) predating this plan; it is
not JWT/RBAC and was **not touched by Sprint 1 Part 3**, consistent with this Part's explicit
"do not implement security" scope and Sprint-1-Backend-Refactoring-Plan.md REF-021 (becomes
blocking only once the first Controller is added, not before).

### 2.8 Reference Number Generation Framework

| | |
|---|---|
| Purpose | Provide a generic, configurable sequential/pattern-based unique-identifier generator (module-scoped prefix + sequence + optional date component) as a shared technical utility, rather than each module hand-rolling its own numbering logic. |
| Dependencies | 2.1 Package Structure (`common` placement), 2.3 Base Entity (for any backing sequence/counter table it manages). |
| Reusable by all modules | Yes, by inference — FR-02 (Admission Number generation) is the only fully-specified consumer in the reviewed set, but the same generic need (Employee Code, Fee Receipt Number, Library Card Number, Transfer Certificate Number, etc.) recurs by pattern across modules; no other module's numbering requirement has been formally specified yet. |
| Blocked by ADR-003 / DG-SIS-001 | No. This is a pure generation utility: given a scheme, it returns the next number. **What triggers a call to it, and for which entity** (e.g., FR-02's Admission-Number-at-Confirm-Enrollment) is module orchestration logic, unaffected by the framework's own design. ADR-003 already settled that Admission's Service layer — not SIS's — triggers the FR-02 sequence this framework will eventually be called from; that ownership question is resolved, not open. |

The framework's own design contains no business rule and no entity design — it is a generic
`generateNext(scheme) -> String` capability. The *scheme configuration* per module (e.g.,
"admission number = AY + sequence, reset yearly") is module-level configuration data supplied
later, during module implementation, not designed here.

**Implementation status (2026-08-05): ✅ Implemented** (ahead of schedule, predating this Part).
`common/generator/` — `ReferenceNumberGenerator`/`ReferenceNumberGeneratorImpl`,
`ReferenceNumberType`, `ReferenceSequence`/`ReferenceSequenceRepository`. Not modified by
Sprint 1 Part 3.

### 2.9 Utility Components

| | |
|---|---|
| Purpose | Stateless, cross-module helper components with no business meaning of their own: date/time handling (academic-year-aware date math), string/formatting helpers, pagination helpers, ID/UUID helpers. |
| Dependencies | 2.1 Package Structure. |
| Reusable by all modules | Yes. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ✅ Implemented.** **Sprint 1 Part 3 added
`common/util/PageRequestUtils.java`** — converts basic page/size/sort parameters (ASD Part-1 §9)
into a Spring Data `Pageable`, applying the shared defaults/clamping in the new
`PaginationConstants` (2.11) uniformly; a pure, stateless conversion with no business meaning.
Tested by `PageRequestUtilsTest`. Also implements a "base reusable interface" from item 9 of the
Sprint 1 Part 3 task: `common/mapper/EntityMapper.java`, a generic `toDto`/`toEntity` contract
per BMDD Part-1 §8 Mapper Standards. Further utilities (date/time helpers, ID/UUID helpers) are
left for when a concrete, non-speculative need is identified — not added preemptively.

### 2.10 Shared Enums

| | |
|---|---|
| Purpose | House enumerations genuinely shared across two or more modules' entities/DTOs (e.g., a generic Approval/Record-status enum used wherever a workflow-status field recurs) — kept out of `common` on a per-value, reviewed basis so a module-specific enum is never miscategorized as shared. |
| Dependencies | 2.1 Package Structure. |
| Reusable by all modules | Yes, for the specific enums that qualify — this is a curated, not catch-all, set. |
| Blocked by ADR-003 / DG-SIS-001 | No — no `Student`-specific enum (e.g., anything describing Class/Section state) is placed here while DG-SIS-001 is open; any such enum stays module-local until the SIS domain model itself is finalized. |

**Implementation status (2026-08-05): ✅ Implemented, deliberately narrow.**
`common/enums/ResponseStatus.java` (SUCCESS/FAILURE) and `common/enums/ErrorCode.java` already
existed; **Sprint 1 Part 3 extended `ErrorCode` with `BUSINESS_RULE_VIOLATION` and
`INTEGRATION_ERROR`**, completing the pairing with the new exception types (2.4). No SIS/module-
adjacent enum was added, consistent with this section's own DG-SIS-001 reasoning.

### 2.11 Common Constants

| | |
|---|---|
| Purpose | Centralize genuinely cross-module constant values: the request-identifier header name (ASD Part-1 §9), default pagination page size, standard date/time format patterns, shared Role-name constants. |
| Dependencies | 2.1 Package Structure. |
| Reusable by all modules | Yes. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ✅ Implemented.** The request-identifier header/MDC-key
names already lived in `common/logging/CorrelationIdConstants.java` (not duplicated here).
**Sprint 1 Part 3 added `common/constant/PaginationConstants.java`**
(`DEFAULT_PAGE_NUMBER`/`DEFAULT_PAGE_SIZE`/`MAX_PAGE_SIZE`) — documented explicitly as
engineering defaults, since no approved document fixes a specific pagination default/maximum.
Standard date/time format patterns were not added: `Instant` fields already serialize as
ISO-8601 by Spring Boot's default Jackson configuration with no explicit pattern constant
needed. Shared Role-name constants remain out of scope pending Security Foundation (2.7).

### 2.12 Logging Strategy

| | |
|---|---|
| Purpose | Implement structured (non-free-text) logging with request-identifier propagation Controller→Service→Repository, the fixed log-level convention (ERROR/WARN/INFO/DEBUG per BMDD Part-4 §31), Sensitive/PII redaction applied uniformly at every layer, and routing to the single centralized log-aggregation destination only. |
| Dependencies | 2.1 Package Structure, 2.5 Response Wrapper (status categories drive log level), 2.7 Security Foundation (identity in log context). |
| Reusable by all modules | Yes — BMDD Part-4 §31 fixes this uniformly with no per-module exception. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ✅ Implemented.** `common/logging/` already provided
structured request logging (`RequestLoggingInterceptor`, `LoggingConfig`/`LoggingProperties`)
and correlation-ID propagation (`CorrelationIdFilter`, `MDCUtil`, `CorrelationIdConstants`).
**Sprint 1 Part 3 closed the one remaining gap** (Sprint-1-Backend-Refactoring-Plan.md REF-042):
the correlation ID is now threaded into every `ApiResponse` payload (see 2.5), not just server-side
log lines, completing end-to-end traceability per ASD Part-2 §19.

### 2.13 API Versioning Strategy

| | |
|---|---|
| Purpose | Fix the leading-URI-path-segment versioning mechanism (ASD Part-1 §7), the backward-compatibility rule for when a version increment is required, and the Controller-layer-only isolation of version-specific behavior (Service-layer Business Rules never fork by version). |
| Dependencies | 2.1 Package Structure. |
| Reusable by all modules | Yes — one versioning convention for every API Group. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ⏳ Not yet realized in code — correctly so.** This is a
Controller-layer URI convention (leading `/api/v1/...` path segment); with no module Controller
written yet (none is in scope for Sprint 1), there is no concrete artifact to add. Realized when
the first module Controller is built in a later sprint.

### 2.14 Configuration Management

| | |
|---|---|
| Purpose | Fix Spring profile structure for environment-specific configuration (Development/Testing/Staging/Production, per Appendix-N), externalized secrets handling, and the shared `ConfigurationService` contract point (owned by `administration`, BMDD Part-1 §6) that modules will read runtime-tunable values through. |
| Dependencies | 2.2 Folder Structure (resource-root location), 2.1 Package Structure. |
| Reusable by all modules | Yes. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ⚠️ Partially implemented.** Externalized-secrets handling
is done (`application.properties` reads `${DB_URL}`/`${DB_USERNAME}`/`${DB_PASSWORD}` with no
committed credential, per Sprint-1-Backend-Refactoring-Plan.md REF-020). Spring profile files
for Development/Testing/Staging/Production (Appendix-N) do not yet exist — only a single
`application.properties` — and the `ConfigurationService` contract point is `administration`
package work, not started. Not addressed by Sprint 1 Part 3 (not in its item list).

### 2.15 Common Configuration

| | |
|---|---|
| Purpose | Application-wide Spring Boot configuration beyond per-environment values: JPA/Hibernate defaults (lazy-fetch-by-default per BMDD Part-3 §29), Jackson/JSON serialization defaults (matching ASD Part-1 §8's JSON-only content-type rule), transaction-manager defaults (BMDD Part-2 §17), and the Redis client configuration backing both caching (BMDD Part-3 §22) and refresh-token tracking (ASD Part-2 §14). |
| Dependencies | 2.14 Configuration Management. |
| Reusable by all modules | Yes. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ✅ Implemented.** `common/config/` —
`ApplicationConfig`, `BeanConfig`, `CacheConfig`, `ClockConfig`, `JacksonConfig`, `AsyncConfig`,
`OpenApiConfig`. Not modified by Sprint 1 Part 3. Known gap tracked separately:
`CacheConfig` still uses an in-process `ConcurrentMapCacheManager` rather than the Redis-backed
manager MDB Part-1 §3 fixes (Sprint-1-Backend-Refactoring-Plan.md REF-032, Medium, not in this
Part's scope).

### 2.16 Coding Standards

| | |
|---|---|
| Purpose | Wire the required build-time linting/static-analysis gate (BMDD Part-4 §34), the FR/BR/NFR/Entity-ID traceability documentation-comment convention on public Service/Repository methods, and the six-sub-package-per-module shape (BMDD Part-1 §4) as an enforced, not aspirational, structure. |
| Dependencies | 2.1 Package Structure, 2.2 Folder Structure. |
| Reusable by all modules | Yes — this is process/tooling, applied identically everywhere. |
| Blocked by ADR-003 / DG-SIS-001 | No. |

**Implementation status (2026-08-05): ⏳ Not implemented.** No build-time linting/static-analysis
gate is wired into `pom.xml` yet; the six-sub-package-per-module shape is followed by convention
in the Admission/Academic modules but not enforced by tooling. This is process/tooling work, not
a code artifact, and was not in Sprint 1 Part 3's scope.

### 2.17 Development Sequence

The dependency chain above fixes the build order within Sprint 1:

1. **2.1 Package Structure** + **2.2 Folder Structure** — nothing else can start without these.
2. **2.15 Common Configuration** + **2.14 Configuration Management** — the application must boot before anything built on top of it can be verified.
3. **2.3 Base Entity and Audit Support** — needed before any entity (including future module entities) can be written.
4. **2.5 Standard API Response Wrapper** → **2.4 Global Exception Handling** → **2.6 Validation Framework** — this trio is built in this order because the handler and the validation short-circuit both render through the wrapper.
5. **2.7 Security Foundation** — depends on the wrapper and handler existing so that auth/authz failures render correctly.
6. **2.12 Logging Strategy** — depends on the response wrapper (status-to-log-level mapping) and security foundation (identity in log context).
7. **2.9 Utility Components**, **2.10 Shared Enums**, **2.11 Common Constants**, **2.8 Reference Number Generation Framework** — independent of each other, built in parallel once 2.1-2.3 exist.
8. **2.13 API Versioning Strategy** — a Controller-layer convention, can be fixed any time after 2.1 but must be in place before the first module Controller is written in Sprint 2.
9. **2.16 Coding Standards** enforcement (linting gate, traceability-comment convention) is wired into the build from step 1 onward, not deferred to the end — it is a continuous discipline, not a discrete build item with its own dependency slot.

## 3. Explicitly Out of Scope for Sprint 1

- `AuditLogService`, `ConfigurationService`, `ApprovalRequestService`, `DocumentService`,
  `NotificationService`, `UserService`, `RoleService` — these are `administration` package
  *business* services (BMDD Part-1 §6). Sprint 1 fixes the audit-column shape (2.3) and the
  configuration-read contract point (2.14) they will plug into, but does not implement them;
  they are `administration`-module work, sequenced after this foundation.
- Any Business Rule (Appendix-C v1.1) enforcement — Business Rules are enforced exclusively in
  a module's Service layer (BMDD Part-2 §18), which does not exist until module implementation
  begins.
- Any entity, repository, DTO, mapper, service, or controller for Admission, SIS, Attendance,
  Examination, Fees, Library, Transport, HR & Payroll, Communication, Reports, or Academic.
- Event Publisher/Consumer implementation (BMDD Part-1 §9) — the pattern is a cross-module
  communication mechanism; with no modules built yet, there is nothing to publish or consume.
  Its scaffolding may be reasonably pulled into Sprint 1 as a stretch item if the team elects to,
  but it is not required for module development to begin.
- Background Job scheduling infrastructure, external-integration adapters (Payment/SMS/Email/
  GPS/UIDAI/Tally/SSO), and file-processing/malware-scanning infrastructure (BMDD Part-3 §25-27)
  — these are real, cataloged foundation-adjacent needs but are not named in the Sprint 1 scope
  given by the objective (items 1-17); flagged here so they are not silently forgotten, not
  recommended for silent inclusion.

## 4. Sprint 1 Deliverables

1. Package/folder structure in place, including the `common` package (`config/`, `security/`,
   `exception/`, `util/`, plus the Sprint 1 elaborations in §2.3/2.10/2.11) per MDB Part-1 §6.
2. Bootable Spring Boot 3 application shell (2.14, 2.15) with environment profiles for
   Development/Testing/Staging/Production.
3. Base entity class implementing the Common Columns baseline (2.3).
4. Centralized global exception handler covering all five exception categories (2.4).
5. Standard API response wrapper, including pagination metadata and the Reports-module Data
   Currency Indicator hook (2.5).
6. Validation framework: Bean Validation wiring + documented cross-field-validation pattern (2.6).
7. Security foundation: JWT auth, RBAC authorization scaffold, refresh-token flow, CORS/CSRF
   config, rate-limiting hook (2.7).
8. Reference number generation framework (2.8).
9. Utility component library, shared enum set, and common constants (2.9-2.11).
10. Logging strategy implemented and wired to the centralized aggregation pipeline (2.12).
11. API versioning convention fixed and demonstrated on a placeholder endpoint (2.13).
12. Coding-standards gate (lint/static-analysis) active in the build pipeline (2.16).
13. This document and its cross-references, kept current as the foundation lands.

## 5. Sprint 1 Exit Criteria

- The application boots cleanly in every defined environment profile with no module-specific
  code present.
- A placeholder Controller → Service → Repository slice (no business meaning, deleted before
  Sprint 2 or kept as a smoke-test fixture) round-trips through the response wrapper, exception
  handler, validation framework, and security foundation successfully, proving the layers
  compose correctly end-to-end (mirrors the request-flow description in BMDD Part-2 §19).
- Every one of the 17 components in §2 has passed review against its own standard's source
  document (BMDD Parts 1-4, ASD Parts 1-2) — no foundation component contradicts the standard
  it implements.
- Coding-standards gate (2.16) is enforced on every commit, not merely documented.
- No Sprint 1 deliverable contains Admission/SIS/Attendance/Examination/Fees/Library/Transport/
  HR/Communication/Reports/Academic business logic, entity, or endpoint.

## 6. Consistency Review Against the Approved Documentation Repository

- **No contradiction found** between this plan and BMDD Parts 1-4, ASD Parts 1-2, ADR-001,
  ADR-002, or the Phase 4-13 SIS/Admission design chain.
- **ADR-003 and DG-SIS-001 do not block any Sprint 1 component.** Both concern the `Student`
  entity's field shape and creation-time semantics (SIS module, Phase 4.2/4.4/4.5/4.6/4.7,
  currently gated on a pending Requirement Owner decision per Phase 13). Sprint 1 builds no
  entity, so there is nothing for either Open item to block. This is confirmed per-component in
  §2's "Blocked by ADR-003 / DG-SIS-001" column.
- **Correction from the prior draft of this plan:** an earlier version of this document treated
  the `common` package as an undocumented gap requiring new Backend Architecture Team
  ratification, on the reasoning that BMDD Part-1 §3 lists only 12 packages (11 business modules
  + `administration`) with no named home for cross-cutting technical infrastructure. That
  reasoning was incorrect. MDB Part-1 §6 — the document BMDD Part-1 explicitly consolidates —
  already fixes a `common/` package (`config/`, `security/`, `exception/`, `util/`) alongside the
  11 modules and `administration`. BMDD Part-1 §2-3's summary omits this sub-package when
  restating MDB's package tree, but per BMDD Part-4 §39 a conflict between BMDD and the earlier,
  more foundational MDB is resolved in favor of MDB — BMDD "does not have authority to silently
  override" it. `common` is therefore already-approved baseline architecture, not a new
  proposal, and needs no ADR or ratification step. §2.1 and §4 above have been corrected
  accordingly.
- No update to ADR-001, ADR-002, ADR-003, DG-SIS-001, or the Phase 4-13 documents is required or
  performed by this plan.

## 7. Sprint 2 Entry Criteria

- All Sprint 1 exit criteria (§5) met.
- DG-SIS-001 remains tracked as a live blocker for SIS/Admission module work specifically (Phase
  4.2 onward) — Sprint 2 module-development sequencing should schedule non-SIS/non-Admission
  module work (e.g., Academic Master Data, per ADR-001) ahead of SIS/Admission if DG-SIS-001 is
  still unresolved when Sprint 2 begins, to avoid idling a team on a known-blocked module.
- The Published SIS Service Interface gap and the Phase 9 §Findings question-4 items (Confirm-
  Enrollment operation identity, transaction boundary, compensating/rollback behavior,
  `fullName`/`dob` nullability) remain tracked exactly as already recorded in the Design Artifact
  Index's "Open blockers across the design set" — Sprint 1 does not resolve, and does not need
  to resolve, any of them.

---

Sprint 1 Backend Foundation Plan is finalized.
