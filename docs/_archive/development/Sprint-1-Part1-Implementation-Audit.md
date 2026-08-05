---
status: Audit — no code changed by this document
date: 2026-08-05
audience: Backend Architecture Team, Preetam Sinha
references: Sprint-1-Backend-Foundation-Plan.md, MDB Part-1, BMDD Parts 1-2, DDD Part-1 §7, ASD Part-1 §10
---

# Sprint 1 — Backend Foundation, Part 1: Implementation Audit

Triggered by a request to implement the 10 Part-1 foundation components. Before writing any
code, `git log -- backend` showed the foundation — and substantially more — already exists,
committed under history `Phase 1.1` through `Phase 3.7` (30 Jul 2026), predating this
documentation-planning thread. This audit reports what already exists against the 10 requested
components, and flags defects/deviations found along the way. **No code was written or modified
to produce this audit.**

## 1. Per-Component Status

| # | Component | Status | Location | Notes |
|---|---|---|---|---|
| 1 | Spring Boot project structure | Exists, **build likely broken** | `backend/pom.xml`, `backend/mvnw` | See §2.A-B. |
| 2 | Package structure per MDB Part-1 | Exists, **deviates from MDB Part-1 §6** | `com.shakticode.schoolerp.*` | See §2.C-D. |
| 3 | Common configuration package | Exists | `config/` (7 classes) | See §2.F for one deviation. |
| 4 | Global exception handling | Exists, partial | `exception/` (5 classes) | Covers Validation/Conflict/Unhandled; no distinct Business Rule or Integration exception type. |
| 5 | Standard ApiResponse wrapper | Exists | `common/response/ApiResponse.java`, `PageMetadata.java` | Good quality; see minor note below. |
| 6 | BaseEntity with audit fields | Exists, **incomplete** | `common/base/BaseEntity.java`, `BaseAuditEntity.java` | Missing soft-delete flag (see §2.G). |
| 7 | Audit support | Exists, labeled placeholder | `audit/JpaAuditingConfig.java` | `AuditorAware` hardcoded to `"system"`, explicitly commented as temporary pending Security Foundation. |
| 8 | Utility package | **Not implemented** | `util/` (package-info only) | Genuine gap. |
| 9 | Common constants | **Not implemented** | `constant/` (package-info only) | Genuine gap. |
| 10 | Shared enums | Exists, narrow | `common/enums/ErrorCode.java`, `ResponseStatus.java` | Scoped to the response/exception system only; no general-purpose shared enum (e.g. a record-status enum) exists yet. |

Also present but **outside today's 10-item request**: `common/generator/` (a Reference Number
Generator, including an `ApplicationReferenceGenerator` — Admission-specific), `logging/` (a full
correlation-ID + MDC logging implementation), `security/SecurityConfig.java` (Spring Security
wired with `permitAll()` on every request — not JWT/RBAC, but present), and `common/event/`
(package-info only, unimplemented).

## 2. Defects and Deviations Found

**A. `pom.xml` almost certainly does not build.**
- `spring-boot-starter-parent` is pinned to version `4.1.0`, which does not exist as a released
  Spring Boot artifact. MDB Part-1 §3 fixes the stack as "Java 21 + Spring Boot 3" — not 4.
- `spring-boot-starter-webmvc` is not a real artifact (the correct id is
  `spring-boot-starter-web`).
- Four test-scope dependencies are not real artifacts:
  `spring-boot-starter-data-jpa-test`, `spring-boot-starter-data-redis-test`,
  `spring-boot-starter-security-test`, `spring-boot-starter-validation-test`,
  `spring-boot-starter-webmvc-test`. (Spring Boot ships one `spring-boot-starter-test`, not a
  per-starter test variant.)
- `config/JacksonConfig.java` imports `tools.jackson.databind.*` — the Jackson **3.x** package
  namespace (Jackson 2.x, which Spring Boot 3 ships, uses `com.fasterxml.jackson.databind.*`).
  This is internally consistent with the `4.1.0` parent but not with MDB Part-1 §3's fixed
  Spring Boot 3 / Java 21 stack.
- Net effect: the pom's dependency coordinates and at least one class's imports assume a
  Spring Boot 4 / Jackson 3 toolchain that contradicts the approved stack and, as far as
  verified here, does not exist to resolve against.

**B. `./mvnw` cannot run as committed.** `.mvn/wrapper/maven-wrapper.properties` is missing, so
the wrapper script fails immediately with "cannot read distributionUrl property." Local
`java -version` reports Java 23, not the fixed Java 21 (MDB Part-1 §3) — not itself a repo
defect, but relevant to whether a build attempted locally would match the approved toolchain.

**C. Root Java package is `com.shakticode.schoolerp`,** not `com.schoolerp` as MDB Part-1 §6's
package tree and §9 naming convention example (`com.schoolerp.fee`) fix. The Maven `groupId` is
a third, different spelling: `com.shaktierp`. Three spellings (`shaktierp` / `shakticode` /
`schoolerp`) currently coexist across `pom.xml` and the source tree.

**D. `config/`, `security/`, `exception/`, `util/`, `audit/`, `constant/`, `validation/`, `dto/`,
`mapper/`, `logging/` are top-level packages, siblings of `common/`** — not nested inside
`common/` as MDB Part-1 §6's package tree explicitly shows (`common/config`, `common/security`,
`common/exception`, `common/util`). `common/` itself currently holds only `base/`, `response/`,
`enums/`, `generator/`, `event/`.

**E. `application.properties` commits a plaintext PostgreSQL password to git**
(`spring.datasource.password=650af3ec39e25c157bee9842`), independent of Sprint 1 scope but
worth flagging as a secret-hygiene issue.

**F. `CacheConfig` uses `ConcurrentMapCacheManager`** (in-process, non-distributed) rather than
Redis, despite MDB Part-1 §3 fixing Redis as the project's cache technology.

**G. `BaseAuditEntity` has no soft-delete field.** DDD Part-1 §7's Common Columns baseline
(cited by BMDD Part-2 §11 and by the Sprint 1 plan's §2.3) is surrogate ID + optimistic-lock
version + audit columns + soft-delete — the existing `BaseEntity`/`BaseAuditEntity` pair covers
the first three but not soft-delete.

**H. `GlobalExceptionHandler` does not yet distinguish Business Rule and Integration exceptions**
as their own categories (BMDD Part-2 §16 fixes five: Validation, Business Rule, Integration,
Concurrency, Unhandled/System). Validation, Concurrency (via
`ObjectOptimisticLockingFailureException`), and Unhandled are covered; `ResourceNotFoundException`
and `ConflictException` exist but there is no `BusinessRuleException` type carrying an
Appendix-C rule ID, and no `IntegrationException` type.

**I. Admission and Academic modules, and a working (if permissive) Security configuration,
already exist**, directly contradicting today's task's explicit "do not create Admission, SIS,
Academic or any ERP module" / "do not implement security yet" instructions. This is prior
history (`Phase 2.x`, `Phase 3.x` commits), not something produced by this audit or by today's
request — flagged for your awareness and decision, not altered.

## 3. Not a Finding

`ApiResponse`/`PageMetadata` (item 5), the `config/` package's remaining six classes (item 3),
and `JpaAuditingConfig`'s labeled TODO (item 7) read as sound, idiomatic Spring Boot code with
no contradiction against the approved documentation set beyond what's listed in §2.

## 4. Recommendation

No further code was written pending your direction on:
1. Whether to fix `pom.xml`/`mvnw` (§2.A-B) before anything else, since nothing in this project
   can be verified to build until that's resolved.
2. Whether to realign the package root and nesting (§2.C-D) to match MDB Part-1 §6, or keep the
   current structure and treat MDB as needing a documented exception.
3. Whether/how to address the pre-existing Admission/Academic/Security code (§2.I) relative to
   today's foundation-only scope.
4. Whether to fill the two genuine gaps (Utility package, Common constants — items 8-9) and the
   partial gaps (soft-delete field, Business Rule/Integration exception types) once 1-3 are
   settled.
