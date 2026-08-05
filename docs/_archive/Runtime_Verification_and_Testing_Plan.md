---
status: Draft — pending approval
date: 2026-08-04
scope: Foundation + Academic module (AcademicSession, AcademicClass) + Admission module (Application, SeatAllocation) + ReferenceSequence
purpose: Verification/testing plan only — no code, no architecture, no implementation changes
---

# Runtime Verification and Testing Plan

## 0. What this plan is checking

Based on the current backend source tree (`backend/src/main/java/com/schoolerp`), four things exist and are in scope:

- **Foundation**: `BaseEntity`/`BaseAuditEntity` (IDENTITY PKs, `@Version` optimistic locking, JPA auditing), `GlobalExceptionHandler`, `ApiResponse`/`PageMetadata` envelope, `CorrelationIdFilter`, `SecurityConfig` (stateless, CSRF disabled, **`permitAll()` on every request** — no authentication is wired up yet), `ReferenceNumberGenerator`/`ReferenceSequence`.
- **Academic module**: `AcademicSession` (`academic_sessions`), `AcademicClass` (`classes`). Only these two entities exist — Section, Subject, GradingScheme, ClassSubjectMap from ADR-001 are **not yet implemented**.
- **Admission module**: `Application` (`applications`), `SeatAllocation` (`seat_allocations`). `classAppliedId`/`classId` and `academicSessionId` on these are plain `Long` FKs with **no `@ManyToOne`/DB foreign-key constraint** — referential integrity is enforced only in the Service layer by calling Academic module's services.
- **Reference generation**: only `ReferenceNumberType.APPLICATION` (`APP-<YEAR>-<6-digit-seq>`) is implemented; `ADMISSION_NUMBER`, `INVOICE`, `EMPLOYEE_CODE` are not.

Two things are notably **not yet configured**, and this plan treats fixing them as a prerequisite gate, not an assumption:

1. `backend/src/main/resources/application.properties` currently contains only `spring.application.name=backend` — no datasource, no Hibernate `ddl-auto`, no server port, no logging config.
2. There is no Flyway/Liquibase dependency in `pom.xml` and no `database/` migration scripts — schema creation depends entirely on Hibernate `ddl-auto` (or a manually-run DDL script), which must be decided before first boot.

---

## 1. Runtime Prerequisites

### 1.1 PostgreSQL requirements
- PostgreSQL instance reachable (local Docker or native install). `docker/` and `database/` folders currently only contain `.gitkeep` — no compose file or init SQL exists yet, so provisioning is manual until one is created.
- A dedicated database (e.g. `school_erp_dev`) and a dedicated role with `CREATEDB`/DDL rights on it (not the PostgreSQL superuser) for local dev.
- Version: PostgreSQL 14+ recommended for compatibility with `spring-boot-starter-parent 3.3.5` / Hibernate ORM shipped with Spring Boot's current generation.
- Confirm `pg_hba.conf` / connection settings allow password auth from the app host (or trust for local-only dev).

### 1.2 `application.properties` settings needed before first boot
None of the following exist yet — they must be added (or supplied via env vars / a `application-local.properties` profile) before `./mvnw spring-boot:run` is attempted:

- `spring.datasource.url=jdbc:postgresql://<host>:5432/<db>`
- `spring.datasource.username` / `spring.datasource.password`
- `spring.datasource.driver-class-name=org.postgresql.Driver` (usually auto-detected, safe to be explicit)
- `server.port` (only if the default 8080 conflicts with something else)
- `spring.jpa.open-in-view=false` — should be explicitly set; leaving it default (`true`) silently masks lazy-loading issues and is inconsistent with a service-layer-owns-transactions design.
- Redis: `pom.xml` includes `spring-boot-starter-data-redis`, but no `CacheConfig` review has been done here for connection properties — `spring.data.redis.host`/`port` must be set, **or** Redis auto-configuration must be explicitly excluded if Redis isn't running yet, otherwise context startup will fail on a connection attempt (verify which — see Startup Checklist item 1.2).

### 1.3 Hibernate configuration
- `spring.jpa.hibernate.ddl-auto` — **must be a deliberate decision, not a default.** Options and their consequences for this plan:
  - `validate` — requires schema to already exist (via manual DDL or a migration tool that doesn't exist yet in this repo). Safest for anything beyond first local smoke-test.
  - `update` — lets Hibernate create/alter tables from entities. Fastest path to a first runtime check, but not representative of a real deployment and can silently diverge from the intended DDL (constraints like the `SeatAllocation` composite unique constraint and `AcademicClass.sequence_order` unique constraint should be verified in section 3, not assumed).
  - `create`/`create-drop` — destructive, dev-only, never for anything with data worth keeping.
  - **Recommendation for this verification pass**: use `update` (or `create`) for the *first* boot to let Hibernate materialize the schema from the current entities, then run the Database Verification Checklist (Section 3) against the result before treating it as a baseline.
- `spring.jpa.show-sql` / `spring.jpa.properties.hibernate.format_sql` — enable for this verification pass only, so generated SQL (especially the pessimistic lock query in `SeatAllocationRepository` and the `ReferenceSequence` counter update) can be inspected directly.
- `spring.jpa.properties.hibernate.jdbc.time_zone=UTC` — confirm intended, since `Application.submittedAt` and `BaseAuditEntity.createdAt/updatedAt` are `Instant` (UTC-safe already), but worth confirming no implicit local-zone conversion happens elsewhere.

### 1.4 Required environment variables
No `@Value`/`@ConfigurationProperties` binding to env vars was found beyond what Spring Boot binds automatically from `application.properties` (and `ApplicationConfig` only enables `@ConfigurationPropertiesScan` — no properties classes were located under `constant`/`config` besides `LoggingProperties`). Practical minimum for a clean boot:
- `SPRING_DATASOURCE_URL`, `SPRING_DATASOURCE_USERNAME`, `SPRING_DATASOURCE_PASSWORD` (if externalizing credentials rather than hardcoding in `application.properties`, recommended even for local dev).
- `SPRING_PROFILES_ACTIVE` (e.g. `local`) if a profile-specific properties file is introduced during this verification pass.
- Redis connection vars if Redis is not defaulted to `localhost:6379`.

**Gate**: none of Section 1.2–1.4 exist in the repo today. Confirm the exact values (datasource URL/credentials, ddl-auto choice, Redis availability) with you before first boot — this plan assumes they'll be supplied at execution time, not invented now.

---

## 2. Startup Verification Checklist

Run in this order; each step should be confirmed before moving to the next.

1. **Compile** — `./mvnw -q clean compile` succeeds with no errors (catches annotation-processing/Lombok issues before a full boot attempt).
2. **Spring Boot startup** — `./mvnw spring-boot:run` (or the packaged jar) reaches `Started BackendApplication in ...` with no exception stack trace.
3. **Bean creation** — no `BeanCreationException` / `UnsatisfiedDependencyException` in logs. Specifically watch for:
   - `SecurityConfig.securityFilterChain` bean created without conflicting with any other `SecurityFilterChain` bean.
   - `ClockConfig`, `AsyncConfig`, `CacheConfig`, `JacksonConfig` beans initialize (these exist but weren't inspected in depth here — confirm no missing property dependency).
   - `JpaAuditingConfig` activates `@EnableJpaAuditing` so `createdBy`/`updatedBy` populate (confirm what principal resolver is wired, since `SecurityConfig` currently permits all requests with no authenticated principal — `createdBy`/`updatedBy` may resolve to `null`/`anonymousUser`, which is expected pre-auth but should be confirmed, not assumed).
4. **Entity scanning** — Hibernate logs (with `show-sql`/debug logging) should list all 5 entities: `AcademicSession`, `AcademicClass`, `Application`, `SeatAllocation`, `ReferenceSequence`. Confirm no entity is silently skipped due to package-scan misconfiguration (all are under `com.schoolerp.**`, same root as `@SpringBootApplication`, so default scanning should cover them — verify in logs anyway).
5. **Repository scanning** — confirm all 5 repositories (`AcademicSessionRepository`, `AcademicClassRepository`, `ApplicationRepository`, `SeatAllocationRepository`, `ReferenceSequenceRepository`) are picked up as Spring Data JPA beans (no `NoSuchBeanDefinitionException` when controllers/services start).
6. **Swagger/OpenAPI** — `GET http://localhost:8080/v3/api-docs` returns JSON, and `GET http://localhost:8080/swagger-ui/index.html` renders. Confirm all 9 controller endpoints appear (2 Academic controllers × 6 + 4 endpoints, Admission × 5 + 4 endpoints — see Section 4 for the full list).
7. **Actuator** — confirm which endpoints are actually exposed (`spring-boot-starter-actuator` is a dependency, but no `management.endpoints.web.exposure.include` was found in `application.properties`, so only the Spring Boot default-exposed endpoint, `/actuator/health`, should respond; `/actuator/*` others will 404 until explicitly exposed). At minimum verify:
   - `GET /actuator/health` → `{"status":"UP"}`, including a `db` component showing `UP` once a datasource is connected.

---

## 3. Database Verification Checklist

Once the app has booted once with `ddl-auto=update` (or after running an equivalent manual DDL), connect directly to PostgreSQL (`psql` or a client) and verify:

### 3.1 Tables expected
- `academic_sessions`
- `classes`
- `applications`
- `seat_allocations`
- `reference_sequences`
- (Spring Session / Redis-backed session tables are **not** expected — `SecurityConfig` uses `SessionCreationPolicy.STATELESS`.)

### 3.2 Constraints to verify per table
- **`academic_sessions`**: `id` PK (identity), `session_name` — `UNIQUE`, `NOT NULL`, `VARCHAR(20)`; `status` NOT NULL; audit columns (`created_at`, `updated_at` NOT NULL, `created_by`, `updated_by` nullable); `version` NOT NULL.
- **`classes`**: `id` PK; `class_name` — `UNIQUE`, `NOT NULL`, `VARCHAR(20)`; `sequence_order` — **`UNIQUE`, `NOT NULL`** (confirm this constraint materializes — a unique integer ordering column is easy to miss when eyeballing a table); audit + version columns.
- **`applications`**: `id` PK; `application_reference_no` — `UNIQUE`, `NOT NULL`, `VARCHAR(20)`; `applicant_name` NOT NULL; `dob` NOT NULL; `class_applied_id` NOT NULL (**no FK constraint to `classes` — confirm this is actually absent**, matching the entity comment that referential integrity is Service-layer-only for now); `academic_session_id` NOT NULL, `updatable=false` at the JPA level only (**DB-level immutability is not enforced by a constraint** — confirm no trigger/check exists, since none was coded); `category`, `status` NOT NULL enums stored as strings; `submitted_at` NOT NULL; audit + version columns.
- **`seat_allocations`**: `id` PK; **composite `UNIQUE(class_id, academic_session_id)`** — this is the key constraint enforcing "one seat-allocation row per class per session"; `total_capacity`, `rte_quota_capacity`, `seats_filled` (default 0), `rte_seats_filled` (default 0) all NOT NULL; no FK constraints to `classes`/`academic_sessions` (same Service-layer-only pattern as `applications`).
- **`reference_sequences`**: `id` PK; `type` — `UNIQUE`, `NOT NULL`, `VARCHAR(30)` (only one row expected initially: `APPLICATION`); `last_value` NOT NULL (`bigint`).

### 3.3 Indexes
- Confirm PostgreSQL auto-created a unique index backing each `UNIQUE` constraint above (Postgres does this automatically — verify with `\d <table>` rather than assuming).
- No additional secondary indexes are defined in the entities (e.g. nothing on `applications.status` or `applications.academic_session_id` for the search/filter endpoint) — if `searchApplications` is expected to filter by status/session/class at any meaningful data volume, flag this as a **missing-index risk** to raise separately; it's a performance concern, not a correctness blocker for this verification pass.

### 3.4 `reference_sequences` specifically
- Verify the row for `type='APPLICATION'` is created (likely lazily, on first `submitApplication` call, not at startup — confirm which) and that `last_value` increments by exactly 1 per submitted application, under the pessimistic write lock described in the class Javadoc.

### 3.5 Academic tables
- Covered above (`academic_sessions`, `classes`). Confirm empty on fresh boot (no seed data mechanism exists in the codebase).

### 3.6 Admission tables
- Covered above (`applications`, `seat_allocations`). Confirm empty on fresh boot.

---

## 4. API Verification Order

No auth is enforced yet (`permitAll()` on all requests), so every endpoint below is callable directly. Test in this dependency order — later endpoints depend on data created by earlier ones.

**Step 1 — Academic Session** (`/api/v1/academic/sessions`)
1. `POST /api/v1/academic/sessions` — create a session (expect `PLANNED` status per `AcademicSessionStatus` default, confirm actual default in `AcademicSessionServiceImpl`).
2. `GET /api/v1/academic/sessions/{id}` — fetch it back.
3. `POST /api/v1/academic/sessions/{id}/status` — transition it (confirm allowed transitions, e.g. `PLANNED → ACTIVE`).
4. `GET /api/v1/academic/sessions/current` — confirm it returns the session once `ACTIVE` (this is what `Application.academicSessionId` auto-resolves from at submission time — critical to verify before Step 4 below).
5. `PATCH /api/v1/academic/sessions/{id}` — update non-status fields.
6. `GET /api/v1/academic/sessions` — list.

**Step 2 — Academic Class** (`/api/v1/academic/classes`)
1. `POST /api/v1/academic/classes` — create at least 2–3 classes with distinct `sequence_order` values (test the unique constraint by attempting a duplicate — expect a 409/validation error, see Section 6).
2. `GET /api/v1/academic/classes/{id}`
3. `PATCH /api/v1/academic/classes/{id}`
4. `GET /api/v1/academic/classes`

**Step 3 — Seat Allocation** (`/api/v1/admission/seat-allocations`) — depends on Step 1 (an `academicSessionId`) and Step 2 (a `classId`).
1. `POST /api/v1/admission/seat-allocations` — create allocation for one class+session pair; confirm `seatsFilled`/`rteSeatsFilled` start at 0.
2. Attempt a **second** `POST` with the same `classId`+`academicSessionId` — expect rejection via the composite unique constraint (Section 6).
3. `PATCH /api/v1/admission/seat-allocations/{id}` — adjust capacity.
4. `GET /api/v1/admission/seat-allocations?academicSessionId=&classId=` — verify filtering.

**Step 4 — Admission Application** (`/api/v1/admission/applications`) — depends on Step 1 (active session, since `academicSessionId` is system-resolved) and Step 2 (`classAppliedId`).
1. `POST /api/v1/admission/applications` — submit; confirm response includes a generated `applicationReferenceNo` matching `APP-<year>-<6 digits>` and `status=SUBMITTED`.
2. `GET /api/v1/admission/applications/{id}`
3. `PATCH /api/v1/admission/applications/{id}` — update editable fields (confirm which fields are blocked once submitted, if any, per `UpdateApplicationRequest`).
4. `GET /api/v1/admission/applications` (paged search) — confirm default sort `submittedAt DESC` and pagination metadata.
5. `POST /api/v1/admission/applications/{id}/decision` — depends on Step 3 (seat allocation must exist for the class+session to validate capacity, if that's how `recordDecision` is wired — confirm in `ApplicationServiceImpl`). Test each `AdmissionDecision` value (`SHORTLISTED`, `WAITLISTED`, `REJECTED`, `ADMITTED`) and confirm `ApplicationStatus` transitions correctly and `SeatAllocation.seatsFilled`/`rteSeatsFilled` increments only on `ADMITTED`.

---

## 5. End-to-End Workflow Verification

Single coherent run tying Section 4 together:

1. **Create Academic Session** → activate it (`PLANNED → ACTIVE`) → confirm `GET .../current` returns it.
2. **Create Academic Class** (e.g. "Class 1", `sequence_order=1`).
3. **Create Seat Allocation** for that class + active session (e.g. `totalCapacity=30`, `rteQuotaCapacity=6`).
4. **Submit Admission Application** for that class — confirm it silently picks up the active session's ID (never accepted as user input) and gets a unique reference number.
5. Submit a **second** application for the same class, category `RTE`.
6. **Record Decision**: `ADMITTED` on application #1 (`GENERAL`) — confirm `seatsFilled` on the seat allocation increments to 1, `rteSeatsFilled` stays 0.
7. **Record Decision**: `ADMITTED` on application #2 (`RTE`) — confirm both `seatsFilled` (2) and `rteSeatsFilled` (1) increment.
8. Repeat `ADMITTED` decisions until `seatsFilled == totalCapacity` (or `rteSeatsFilled == rteQuotaCapacity` for RTE applications) and confirm the *next* admit attempt is rejected (BR-ADM-001 / BR-ADM-003 capacity ceiling enforcement — Section 7).
9. Confirm a `REJECTED`/`WAITLISTED` decision does **not** touch `seatsFilled`/`rteSeatsFilled`.
10. Confirm `ApplicationStatus` never regresses (forward-only lifecycle per the entity Javadoc) — e.g. a decision cannot be recorded twice to move status backward.

---

## 6. Negative Test Cases

| # | Case | Expected result |
|---|---|---|
| 1 | `POST` application with missing required field (`applicantName`, `dob`, `classAppliedId`) | 400, Bean Validation error via `GlobalExceptionHandler` |
| 2 | `POST` application with `dob` in the future or implausible (e.g. age making the applicant too old/young for the class) | 400 if a business-rule check exists — confirm in `ApplicationServiceImpl`; if not implemented, note as a gap, don't assume |
| 3 | `POST` application with `classAppliedId` referencing a non-existent class | Expect a Service-layer validation error (404 or 400) since there's no DB FK to catch this |
| 4 | `POST` application with `aadhaarNumber` not 12 digits (if validated) | 400 |
| 5 | `POST` academic class with duplicate `class_name` or duplicate `sequence_order` | 409 Conflict (`ConflictException`) surfaced via `GlobalExceptionHandler`, backed by the DB unique constraint |
| 6 | `POST` seat allocation with duplicate `(classId, academicSessionId)` | 409 Conflict |
| 7 | `POST` seat allocation with `rteQuotaCapacity > totalCapacity` | 400, if validated in service — confirm, don't assume |
| 8 | `GET`/`PATCH` with non-existent `id` on any resource | 404, via `ResourceNotFoundException` |
| 9 | `PATCH` an academic session's status with an invalid transition (e.g. `CLOSED → ACTIVE`) | 400/409, forward-only status enforcement |
| 10 | Record a decision (`POST .../decision`) on an application already in a terminal state (`ADMITTED`/`REJECTED`) | Rejected — forward-only lifecycle |
| 11 | Attempt `ADMITTED` decision when seat allocation is already at `totalCapacity` | Rejected — capacity ceiling (BR-ADM-001) |
| 12 | Attempt `ADMITTED` decision for an `RTE` application when `rteSeatsFilled == rteQuotaCapacity` even though general seats remain | Rejected — RTE ceiling (BR-ADM-003) |
| 13 | Submit application when no `AcademicSession` is currently `ACTIVE` | Expect an explicit error (no session to resolve `academicSessionId` from), not a silent null or 500 |
| 14 | Concurrent duplicate seat-allocation `POST` (race) | DB unique constraint should catch it even if service-layer check has a race window — confirms defense-in-depth |
| 15 | Malformed JSON / wrong content-type on any `POST`/`PATCH` | 400, handled by `GlobalExceptionHandler`, not a raw stack trace |

---

## 7. Business Rule Verification

Cross-reference against Appendix-C (Business Rules) and the entity Javadocs found in code — verify these are actually enforced, not just documented:

- **BR-ADM-001** — Seat allocation capacity ceiling: `seatsFilled` must never exceed `totalCapacity`. Enforced in Service layer per `SeatAllocation` Javadoc — verify with test case #11 above.
- **BR-ADM-002** — Application reference number must not already exist for the academic year. `ReferenceSequence` Javadoc states the counter never resets per year and relies on global uniqueness across all years to trivially satisfy this — verify by submitting applications across two different (simulated) years if feasible, or at minimum confirm two applications in the same year never collide.
- **BR-ADM-003 / BR-ADM-004** — RTE quota rules: `rteSeatsFilled` must never exceed `rteQuotaCapacity`, and RTE-category applications should be checked against the RTE sub-ceiling specifically (not just the general ceiling) — verify with test case #12.
- **Multi-Session Handling / Academic Year Strategy** (Appendix-H v1.1 §2.9–2.10) — `academicSessionId` on `Application` must be system-assigned from the current Active session at submission time, never user-supplied, and immutable after creation (`updatable=false` at JPA level — confirm the DTO layer, `CreateApplicationRequest`, doesn't even expose the field for input).
- **Forward-only status lifecycles** — both `ApplicationStatus` and `AcademicSessionStatus` are documented as forward-only; verify no endpoint allows a backward transition.
- **Cross-module dependency rule** (BMDD Part-1) — Admission's `AcademicSessionService` (in `admission/service/`) should now delegate to the real Academic module implementation rather than a placeholder; confirm `admission/service/AcademicSessionServiceImpl.java` calls into `academic`'s service and doesn't still contain stub/placeholder logic left over from before Phase 3.

---

## 8. Reference Number Verification

- Format: `APP-<4-digit-year>-<6-digit-zero-padded-sequence>` (e.g. `APP-2026-000001`), per `ReferenceNumberType.APPLICATION`.
- Uniqueness: confirm the DB `UNIQUE` constraint on `application_reference_no` is the backstop, and that the pessimistic lock in `ReferenceSequenceRepository`/`ReferenceNumberGeneratorImpl` prevents duplicate sequence values under concurrent submissions (test with parallel `POST` requests if load-testing tooling is available; otherwise note as a manual concurrency risk to revisit).
- Sequence never resets per calendar year (by design, per the class Javadoc) — confirm this is intentional and acceptable versus an implicit assumption that it *should* reset each year (worth a direct confirmation from you since it affects how "year" in the reference number relates to the running counter).
- Confirm `last_value` in `reference_sequences` increments exactly once per successful submission, and **not at all** on a failed/rolled-back submission (i.e. the increment must be transactional with the application insert, not committed independently).

---

## 9. Common Failure Scenarios to Watch For

- **App fails to start**: datasource connection refused (Postgres not running / wrong port/creds) — most likely first failure given `application.properties` currently has zero datasource config.
- **App fails to start (Redis)**: `spring-boot-starter-data-redis` is on the classpath; if Redis isn't running and autoconfiguration isn't excluded, expect a connection-related startup failure — confirm whether `CacheConfig` actually requires a live Redis connection at boot or degrades gracefully.
- **Schema mismatch**: if `ddl-auto=validate` is used against a stale/manually-created schema, expect `SchemaManagementException` listing exact column/constraint mismatches — use this as a genuine verification signal, not just an obstacle.
- **Silent constraint gaps**: entities using plain `Long` FK fields (`classAppliedId`, `academicSessionId`, `classId`) have **no DB-level foreign key** — orphaned references (e.g. an application pointing at a deleted class) are only preventable in the Service layer. Confirm this is accepted as-is for current phase, not an oversight to flag.
- **`createdBy`/`updatedBy` always null**: expected pre-authentication (no principal resolver wired since `SecurityConfig` permits all requests anonymously) — confirm this is understood as a known/deferred gap, not a bug, before it causes confusion during review.
- **Optimistic lock exceptions** (`ObjectOptimisticLockingFailureException`) on `SeatAllocation` updates under concurrent decision-recording — expected behavior given `@Version`, but confirm `GlobalExceptionHandler` maps it to a sane HTTP status (409) rather than a raw 500.
- **Swagger UI showing stale/incorrect schema** if DTOs and actual validation annotations drift — cross-check a couple of request bodies against actual `@Valid` constraints, not just what Swagger displays.
- **Actuator returning 404 for endpoints assumed exposed** — remember only `/actuator/health` is exposed by default; don't treat a 404 on `/actuator/metrics` etc. as a bug unless exposure was explicitly configured.

---

## 10. Final Production Readiness Checklist

Explicitly **not** expected to be ready yet at this phase — listed here so scope is clear, not as blockers for *this* runtime verification pass:

- [ ] Authentication/authorization wired (currently `permitAll()` on everything — acceptable for internal dev verification, not for any shared/production environment).
- [ ] `spring.jpa.hibernate.ddl-auto` set to `validate` (or removed) against a schema owned by an actual migration tool (Flyway/Liquibase), not Hibernate auto-DDL.
- [ ] A migration tool and initial migration scripts added (`database/` is currently empty).
- [ ] `docker/` populated with a reproducible local Postgres (+ Redis, if kept) compose setup, so this whole verification pass is reproducible by anyone, not dependent on manually-provisioned local state.
- [ ] Actuator endpoint exposure reviewed and locked down (`management.endpoints.web.exposure.include` set deliberately, sensitive endpoints protected).
- [ ] Missing-index review for search/filter endpoints (`applications` search, `seat-allocations` search) once realistic data volumes are known.
- [ ] Structured logging / correlation ID propagation confirmed end-to-end (`CorrelationIdFilter` exists — verify it's actually populating MDC and appearing in log output).
- [ ] Externalized secrets (datasource credentials, any future JWT signing keys) — confirmed out of `application.properties` and out of version control.
- [ ] CORS configuration (`CorsConfig` exists) reviewed against actual frontend origin(s) rather than defaults.
- [ ] Load/concurrency test on `ReferenceNumberGenerator` and `SeatAllocation` capacity checks specifically (both have documented locking behavior that only matters under concurrent load).

---

## Next Step

This is a verification plan only — no runtime execution, schema creation, or code changes have been made. Please confirm before I proceed with Section 1's gate (datasource URL/credentials, `ddl-auto` choice for the first boot, and Redis availability) and begin executing Section 2 (Startup Verification).
