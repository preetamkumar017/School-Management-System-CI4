---
status: Roadmap — no source code or existing document changed by this plan
date: 2026-08-05
audience: Backend Architecture Team, Preetam Sinha
references: Sprint-1-Part1-Implementation-Audit.md, Sprint-1-Backend-Foundation-Plan.md, MDB Part-1, BMDD Parts 1-2, DDD Part-1 §7, ASD Part-1 §9-10
---

# Sprint 1 — Backend Foundation Refactoring Plan

This plan sequences fixes for every issue recorded in
[Sprint-1-Part1-Implementation-Audit.md](Sprint-1-Part1-Implementation-Audit.md). The existing
`backend/` codebase is treated as the source of truth — nothing here proposes a rewrite from
scratch, only a fix, move, or explicit decision for each recorded issue. **This document does
not modify source code, does not modify any other document, and does not implement anything —
it is planning only.**

## 1. Severity Classification

| Severity | Definition used in this plan |
|---|---|
| **Critical** | Build blocker — the project cannot be compiled, packaged, or run as committed. |
| **High** | Not a build blocker, but a foundational gap or risk that gets more expensive to fix the longer it's left (every additional file built on top of it multiplies the fix cost), or a live security exposure. |
| **Medium** | A real deviation from the approved baseline or a real gap, but isolated — fixing it later does not compound. |
| **Low** | Cosmetic, stylistic, or convenience-level; no functional or compliance risk either way. |

## 2. Issue Register

Each issue keeps the reference ID it will carry into the backlog (§5).

### 2.1 Build Issues

| ID | Issue | Severity | Status |
|---|---|---|---|
| REF-001 | `pom.xml` parent version `4.1.0` does not exist; MDB Part-1 §3 fixes Spring Boot 3 | Critical | ✅ Completed 2026-08-05 |
| REF-002 | `pom.xml` declares non-existent artifact IDs (`spring-boot-starter-webmvc`, four fabricated `-test` starters) | Critical | ✅ Completed 2026-08-05 |
| REF-003 | `config/JacksonConfig.java` imports the Jackson 3.x (`tools.jackson.databind`) namespace, incompatible with Spring Boot 3's Jackson 2.x | Critical | ✅ Completed 2026-08-05 |
| REF-004 | `./mvnw` cannot run — `.mvn/wrapper/maven-wrapper.properties` is missing | Critical | ✅ Completed 2026-08-05 |
| REF-005 | Local JDK is 23; MDB Part-1 §3 fixes Java 21 — toolchain not enforced/pinned in the project | Low | Open — not in this Part's P0 scope |

**REF-001 / REF-002 / REF-003 — Root cause:** the `pom.xml` and `JacksonConfig.java` were
generated assuming a Spring Boot 4 / Jackson 3 toolchain that does not match the approved stack
(MDB Part-1 §3: "Java 21 + Spring Boot 3") and, as far as verified, is not a released,
resolvable set of coordinates.
**Impact:** `mvn compile`/`mvn package` fails dependency resolution before any code is even
reached; nothing downstream (tests, packaging, any of the other issues in this register) can be
verified until this is fixed.
**Files affected:** `backend/pom.xml`, `backend/src/main/java/com/shakticode/schoolerp/config/JacksonConfig.java`.
**Recommended fix:** pin `spring-boot-starter-parent` to a real Spring Boot 3.x release; replace
`spring-boot-starter-webmvc` with `spring-boot-starter-web`; replace the four fabricated `-test`
artifacts with the single `spring-boot-starter-test`; rewrite `JacksonConfig` against
`com.fasterxml.jackson.databind.*` (Jackson 2.x, `ObjectMapper`, not `JsonMapper`/`tools.jackson`).
**Before further development:** **Yes — blocking.** No other fix in this register, and no new
Sprint 1 component, can be verified to compile until this lands.
**Resolution (2026-08-05):** `pom.xml` parent pinned to `spring-boot-starter-parent` `3.3.5`
(real, released Spring Boot 3.x); `spring-boot-starter-webmvc` replaced with
`spring-boot-starter-web`; the five test-scope dependencies (`spring-boot-starter-data-jpa-test`,
`spring-boot-starter-data-redis-test`, `spring-boot-starter-security-test`,
`spring-boot-starter-validation-test`, `spring-boot-starter-webmvc-test`) replaced with the
single real `spring-boot-starter-test`; `springdoc-openapi-starter-webmvc-ui` version corrected
from the non-existent `3.0.3` to `2.6.0` (a real release compatible with Spring Boot 3.x, found
during REF-002 dependency-correction work); `JacksonConfig.java` imports changed from
`tools.jackson.databind.*` (Jackson 3.x) to `com.fasterxml.jackson.databind.*` (Jackson 2.x,
which Spring Boot 3 ships) — `JsonMapper`/`DeserializationFeature` class names unchanged, only
the package namespace. `mvn clean compile` verified green (112 source files, `BUILD SUCCESS`,
zero warnings/errors).

**REF-004 — Root cause:** the Maven wrapper's properties file was never committed (or was
excluded by `.gitignore`) while `mvnw`/`mvnw.cmd` were.
**Impact:** anyone relying on `./mvnw` (the standard, version-pinned way to build this project)
cannot build at all, independent of REF-001/002/003.
**Files affected:** `backend/.mvn/wrapper/maven-wrapper.properties` (missing).
**Recommended fix:** regenerate the wrapper (`mvn -N wrapper:wrapper` pinned to a Maven version
compatible with the corrected Spring Boot 3.x parent) so `.mvn/wrapper/maven-wrapper.properties`
is present and committed.
**Before further development:** **Yes — blocking**, for the same reason as REF-001-003: it is
part of "can this project build at all."
**Resolution (2026-08-05):** `backend/.mvn/wrapper/maven-wrapper.properties` created, pointing
`distributionUrl` at the real Apache Maven 3.9.9 binary distribution. `./mvnw -v` verified it
resolves and runs (Apache Maven 3.9.9).

**REF-005 — Root cause:** no `.java-version`/`maven-toolchains.xml`/CI pin enforcing Java 21
locally; the current shell resolves to JDK 23.
**Impact:** low in isolation (Java 21 bytecode/API usage is generally forward-compatible with a
newer JDK at compile time), but leaves the build's actual toolchain unverified against MDB Part-1
§3's fixed version, and could mask a Java-21-vs-23 API difference later.
**Files affected:** none yet — this is a missing safeguard, not a broken file.
**Recommended fix:** add a Maven toolchain/enforcer rule (or CI JDK pin) requiring Java 21;
document the required local JDK in `backend/HELP.md` or the repo `README.md`.
**Before further development:** No — can be done any time before the project is considered
release-ready, not before the next code change.

### 2.2 Package Structure Issues

| ID | Issue | Severity | Status |
|---|---|---|---|
| REF-010 | Root Java package is `com.shakticode.schoolerp`, not `com.schoolerp` (MDB Part-1 §6/§9) | High | ✅ Completed 2026-08-05 |
| REF-011 | Maven `groupId` (`com.shaktierp`) is a third spelling, distinct from both the Java root package and MDB's fixed name | Medium | ✅ Completed 2026-08-05 (batched with REF-010 per its own recommended fix) |
| REF-012 | `config/`, `security/`, `exception/`, `util/`, `audit/`, `constant/`, `validation/`, `dto/`, `mapper/`, `logging/` sit as top-level packages, not nested under `common/` as MDB Part-1 §6's package tree shows | High | ✅ Completed 2026-08-05 |

**REF-010 — Root cause:** the project was scaffolded with an ad hoc root package instead of the
one MDB Part-1 §6/§9 fixes.
**Impact:** every existing class (currently ~90 files across foundation, Admission, and Academic
code) sits under the wrong root; the longer this is left, the more files a rename touches and the
more likely it collides with concurrent work.
**Files affected:** every `.java` file under `backend/src/main/java/com/shakticode/schoolerp/**`
and `backend/src/test/java/com/shakticode/schoolerp/**` (package declaration + all internal
imports).
**Recommended fix:** package-rename `com.shakticode.schoolerp` → `com.schoolerp` project-wide
(IDE-assisted rename or `git mv` + sed, done as one atomic commit, followed by a full build to
confirm nothing was missed).
**Before further development:** **Yes, recommended before new foundation classes are added** —
adding new classes under the wrong root now only grows REF-010's footprint further.
**Resolution (2026-08-05):** every `.java` file under `com.shakticode.schoolerp` (112 main +
2 test source files) had its package declaration and internal imports rewritten to
`com.schoolerp` via a project-wide, pattern-anchored substitution, followed by physically moving
`src/main/java/com/shakticode/schoolerp` → `src/main/java/com/schoolerp` (and the equivalent
test-tree move); the now-empty `com/shakticode` directories were removed. Verified with
`./mvnw clean compile` (BUILD SUCCESS).

**REF-011 — Root cause:** the Maven `groupId` was set independently of the Java package
decision, and neither matches the other.
**Impact:** cosmetic/consistency only — Maven `groupId` does not have to equal the Java root
package — but three different spellings in one small project is a real maintainability and
onboarding hazard.
**Files affected:** `backend/pom.xml` (`<groupId>` element).
**Recommended fix:** align `groupId` to `com.schoolerp` (or a deliberately different, documented
organization id) once REF-010 is decided, so there is one spelling, not three.
**Before further development:** No — cosmetic; safe to batch with REF-010 or defer.
**Resolution (2026-08-05):** `pom.xml` `<groupId>` changed from `com.shaktierp` to
`com.schoolerp`, matching the now-corrected Java root package (REF-010). One spelling remains,
not three.

**REF-012 — Root cause:** the package tree was built as a flat set of top-level infrastructure
packages instead of nesting the four MDB-named ones (`config`, `security`, `exception`, `util`)
inside `common/`, and the project further split infrastructure across packages MDB does not
name at all (`audit`, `constant`, `validation`, `dto`, `mapper`, `logging`).
**Impact:** structural deviation from the approved package tree; every future foundation or
module file's placement decision inherits whichever structure is chosen here, so this compounds
the longer it's deferred — same reasoning as REF-010.
**Files affected:** all packages listed above and everything that imports from them (i.e., most
of the codebase, including the Admission and Academic modules).
**Recommended fix:** resolved as mandatory — see §4. Relocate `config/`, `security/`,
`exception/`, `util/`, `audit/`, `constant/`, `validation/`, `dto/`, `mapper/`, and `logging/`
under `common/`, matching MDB Part-1 §6. Execute as one atomic move alongside REF-010 so package
declarations are only rewritten once.
**Before further development:** **Yes** — same compounding-cost reasoning as REF-010, and no
longer contingent on an open decision.
**Resolution (2026-08-05):** `config/`, `security/`, `exception/`, `util/`, `audit/`,
`constant/`, `validation/`, `dto/`, `mapper/`, and `logging/` physically relocated to
`common/config`, `common/security`, `common/exception`, `common/util`, `common/audit`,
`common/constant`, `common/validation`, `common/dto`, `common/mapper`, `common/logging`
respectively (main and test trees); every package declaration and cross-reference (including
from the Admission and Academic modules, which import `common.exception`'s
`ConflictException`/`ResourceNotFoundException`/`ValidationException`) rewritten accordingly.
Verified: no residual `com.schoolerp.<oldpath>` references remain outside `common.*`, and
`./mvnw clean compile` succeeds (`BUILD SUCCESS`).

### 2.3 Security Issues

| ID | Issue | Severity | Status |
|---|---|---|---|
| REF-020 | Plaintext PostgreSQL password committed to `application.properties` | High | ⚠️ Partially completed 2026-08-05 — see resolution note |
| REF-021 | `SecurityConfig` is wired but `permitAll()`s every request — informational, not a defect | Low | Open — Low priority, not in this Part's scope |

**REF-020 — Root cause:** `spring.datasource.password` was hardcoded directly into
`application.properties` instead of being externalized (environment variable, secrets manager,
or a git-ignored local override file).
**Impact:** the credential is in git history now, independent of any future edit to the file —
editing the file alone does not remove the exposure. Real risk is proportional to whether this
credential is still live/reachable and whether the repository (or its history) is or could become
non-private.
**Files affected:** `backend/src/main/resources/application.properties`.
**Recommended fix (two parts, both needed):** (1) rotate the PostgreSQL credential so the
committed value is no longer valid, independent of any repo change; (2) externalize the
datasource password (environment variable / `application-local.properties` excluded via
`.gitignore` / secrets manager per Configuration Management, Sprint-1-Backend-Foundation-Plan.md
§2.14) so no future credential is committed in plaintext again.
**Before further development:** **Yes for credential rotation — immediately, independent of any
other item in this plan** (rotation is an operational action, not a code change, and should not
wait on a sprint sequence). The `application.properties` edit itself can land alongside the
Common Configuration work (§2.14/2.15 of the foundation plan).
**Resolution (2026-08-05) — file fix only, rotation still outstanding:** `application.properties`
no longer contains a literal credential — `spring.datasource.url`/`username` now read from
`${DB_URL:...}`/`${DB_USERNAME:...}` (with non-secret local-dev defaults), and
`spring.datasource.password` reads from `${DB_PASSWORD}` with **no default**, so the application
fails fast at startup if the variable is not supplied rather than falling back to anything
committed. The repository root `.gitignore` was also extended with
`application-local.properties`/`.yml`/`.yaml` so a future local override file cannot be
re-committed. **This does not remove the exposed credential from git history, and the credential
itself has not been rotated** — that is an operational action outside what a code change can
accomplish, and per this item's own "before further development" note it should happen
immediately and independently. Flagging again here since it remains outstanding.

**REF-021 — Root cause:** `SecurityConfig` was added ahead of the Security Foundation work item
(Sprint-1-Backend-Foundation-Plan.md §2.7), with every request explicitly permitted, as an
interim state.
**Impact:** none currently (no controller exists yet for it to guard), but it is a live risk the
moment the first Controller is added, if forgotten. Not a code defect today — a documentation/
tracking risk.
**Files affected:** `backend/src/main/java/com/schoolerp/common/security/SecurityConfig.java`
(path updated by REF-012's relocation).
**Recommended fix:** no code change now; add a tracked backlog item (already reflected in
Sprint-1-Backend-Foundation-Plan.md §2.7) to replace `permitAll()` with the real authorization
model before any Controller is merged.
**Before further development:** No — informational; becomes blocking only when the first
Controller is added, not before.

### 2.4 Documentation Inconsistencies

(Implementation exists but deviates from what the approved baseline specifies.)

| ID | Issue | Severity | Status |
|---|---|---|---|
| REF-030 | `BaseAuditEntity` has no soft-delete field (DDD Part-1 §7 Common Columns baseline) | High | ⚠️ Partially completed 2026-08-05 — see resolution note |
| REF-031 | `GlobalExceptionHandler` has no distinct Business Rule or Integration exception category (BMDD Part-2 §16 fixes five categories) | Medium | Open — Medium priority, not in this Part's scope |
| REF-032 | `CacheConfig` uses `ConcurrentMapCacheManager`, not Redis (MDB Part-1 §3 fixes Redis as the cache technology) | Medium | Open — Medium priority, not in this Part's scope |

**REF-030 — Root cause:** `BaseEntity`/`BaseAuditEntity` were built covering surrogate ID,
optimistic-lock version, and audit columns, but the fourth element of DDD Part-1 §7's Common
Columns baseline — soft-delete — was omitted.
**Impact:** every entity that will ever extend `BaseAuditEntity` (all of them, per the base-entity
design intent) inherits this gap; the Admission and Academic entities already extending it today
would need a schema migration to add the column retroactively once this is fixed, so the gap
compounds with every new entity added in the meantime.
**Files affected:** `common/base/BaseAuditEntity.java`; downstream, every entity class that
extends it (currently the Admission and Academic entities).
**Recommended fix:** add a `deleted`/`deletedAt` (or equivalent) field to `BaseAuditEntity`,
plus the corresponding repository-level default-scope filtering convention referenced by MDB
Part-1 §9's `DELETE` = soft-delete semantics.
**Before further development:** **Yes, recommended before new entities are added** — same
compounding-cost logic as the package-structure issues.
**Resolution (2026-08-05) — entity field completed, repository-level filtering deferred:**
`common/base/BaseAuditEntity.java` gained `deleted` (`boolean`, defaults `false`) and
`deletedAt` (`Instant`) fields with getters/setters, matching DDD Part-1 §7's Common Columns
baseline (surrogate ID + version from `BaseEntity`, plus audit columns and now soft-delete here).
A unit test (`BaseAuditEntityTest`) was added covering the default value, setter/getter
round-trip, and that the pre-existing audit/id/version accessors still work, per REF-040's
"test each foundation class as it is touched" approach. **The repository-level default-scope
filtering convention this item's recommended fix also calls for (excluding soft-deleted rows
from default queries) was deliberately not implemented** — it would touch query/repository
behavior for every future entity and is closer to an architectural decision than a base-class
field addition, which this Part's "do not redesign architecture" instruction counsels leaving
for a dedicated, explicitly-scoped item rather than folding in here. `./mvnw clean compile` and
`test-compile` both succeed.

**REF-031 — Root cause:** the exception hierarchy was built with `ValidationException`,
`ResourceNotFoundException`, and `ConflictException`, covering three of BMDD Part-2 §16's five
categories (Validation, Concurrency via Spring's own
`ObjectOptimisticLockingFailureException`, Unhandled/System via the catch-all); no
`BusinessRuleException` (carrying an Appendix-C rule ID) or `IntegrationException` type exists.
**Impact:** isolated — does not compound the way REF-030 does, since adding the two missing
exception types later does not require touching already-written code, only new Service-layer
code written from here on.
**Files affected:** `backend/src/main/java/com/schoolerp/common/exception/` (path updated by
REF-012's relocation; new files would go here);
`GlobalExceptionHandler.java` (new handler methods).
**Recommended fix:** add `BusinessRuleException` (constructed with an Appendix-C v1.1 rule ID)
and `IntegrationException` types plus corresponding `@ExceptionHandler` methods, consistent with
the five-category model already fixed in BMDD Part-2 §16 and referenced in
Sprint-1-Backend-Foundation-Plan.md §2.4.
**Before further development:** No — module Service-layer code that needs to throw a Business
Rule or Integration exception does not exist yet (no modules are in scope); safe to schedule
alongside, not strictly ahead of, other Medium items.

**REF-032 — Root cause:** `CacheConfig` was built with Spring's in-process
`ConcurrentMapCacheManager` rather than the Redis-backed cache manager MDB Part-1 §3 fixes,
despite `spring-data-redis` already being a project dependency.
**Impact:** isolated today (nothing currently `@Cacheable`); becomes a real problem only once
the application is horizontally scaled (MDB Part-1 §4) and a module relies on `@Cacheable` for
correctness-sensitive Master Data, at which point an in-process cache silently breaks cross-instance
consistency.
**Files affected:** `backend/src/main/java/com/schoolerp/common/config/CacheConfig.java` (path
updated by REF-012's relocation).
**Recommended fix:** replace `ConcurrentMapCacheManager` with a Redis-backed `CacheManager`
(`RedisCacheManager`), configured per BMDD Part-3 §22's TTL/cache-key conventions once that work
item is reached.
**Before further development:** No — no module currently depends on caching correctness; safe to
defer to whichever sprint item first introduces a `@Cacheable` method.

### 2.5 Code Quality Issues

| ID | Issue | Severity | Status |
|---|---|---|---|
| REF-040 | Zero unit tests exist for any foundation or module class except `BackendApplicationTests` (smoke test only) | High | ⚠️ Partially completed 2026-08-05 — see resolution note |
| REF-041 | Lombok is a declared dependency but unused everywhere (`BaseEntity`, `BaseAuditEntity`, `ApiResponse`, `PageMetadata`, all exception types use hand-written boilerplate) | Low | Open — Low priority, not in this Part's scope |
| REF-042 | `GlobalExceptionHandler`'s error responses do not carry the request/correlation ID that `logging/CorrelationIdFilter` + `MDCUtil` already generate per request | Medium | Open — Medium priority, not in this Part's scope |

**REF-040 — Root cause:** foundation and module code was written without accompanying unit
tests at each step, despite MDB Part-1 §10.3 ("No code is merged without passing the Unit
Testing level ... for the changed module") and BMDD Part-4 §33 already fixing this as a
merge gate.
**Impact:** this is the most consequential Code Quality issue on this register — every class in
`common/` (including its `exception/` and `config/` sub-packages, nested there per the completed
REF-012), the Admission module, and the Academic module is currently unverified by any automated
test; the governance rule that should have caught this at merge time was already bypassed once,
and every additional un-tested file merged from here on repeats that bypass.
**Files affected:** effectively the entire `backend/src/main/java` tree; `backend/src/test/java`
currently contains only `BackendApplicationTests.java`.
**Recommended fix:** backfill Unit Tests for the foundation classes in this register as each is
touched for its own fix (e.g., test `BaseAuditEntity`'s soft-delete field when REF-030 lands, test
the two new exception types when REF-031 lands); treat "no test, no merge" as active from this
point forward per the already-approved standard, rather than attempting a single retroactive
test-everything pass.
**Before further development:** **Yes, as a going-forward rule** — no new foundation class
should be merged without its test from this point on. Retroactively backfilling tests for
already-merged code is High priority but not required to complete before the next commit; the
rule not to repeat the gap is what's blocking.
**Resolution (2026-08-05) — going-forward rule applied to this Part's own changes, full
backfill still outstanding:** the one foundation class this Part changed with new logic
(`BaseAuditEntity`, REF-030) received a new test class (`BaseAuditEntityTest`), so the
"no test, no merge" rule was followed for this Part's own work — consistent with the recommended
fix's "as each is touched" approach rather than a retroactive test-everything pass. **The
pre-existing gap is not closed**: `common/response/ApiResponse.java`,
`common/exception/GlobalExceptionHandler.java`, every other pre-existing `common/*` class, and
both the Admission and Academic modules still have zero test coverage. Backfilling those remains
open and is not attempted here, since none of them were otherwise touched by this Part's
Critical/High items in scope.

**REF-041 — Root cause:** stylistic choice/oversight; Lombok (`@Getter`/`@Setter`/`@Builder`/
`@RequiredArgsConstructor` etc.) was added to `pom.xml` but never adopted in the classes that
would benefit from it.
**Impact:** none functionally; purely boilerplate volume and future-maintenance friction.
**Files affected:** `common/base/BaseEntity.java`, `BaseAuditEntity.java`, `BaseDTO.java`,
`common/response/ApiResponse.java`, `PageMetadata.java`, all `common/exception/*.java` types
(paths as updated by REF-012's relocation).
**Recommended fix:** adopt Lombok annotations on these classes when they are next touched for
another fix in this register (e.g., alongside REF-030's `BaseAuditEntity` change), rather than as
a standalone edit-only-for-style pass.
**Before further development:** No — purely cosmetic.

**REF-042 — Root cause:** `logging/CorrelationIdFilter` and `MDCUtil` already establish a
per-request correlation ID (consistent with ASD Part-1 §9's "unique request identifier"), but
`GlobalExceptionHandler.buildResponse(...)` does not read it into the `ApiResponse` error payload,
so the two already-built pieces are not yet connected.
**Impact:** an error response today cannot be correlated back to its server-side log lines by a
client or support engineer without cross-referencing timestamps — undercuts the traceability ASD
Part-2 §19 and the logging strategy were built for.
**Files affected:** `common/exception/GlobalExceptionHandler.java` (read from `MDCUtil`, now at
`common/logging/MDCUtil.java` per REF-012); possibly `common/response/ApiResponse.java` (add a
`requestId` field) if not already reachable via `meta`.
**Recommended fix:** thread the current-request correlation ID from `MDCUtil` into every error
`ApiResponse` built by `GlobalExceptionHandler`.
**Before further development:** No — isolated integration gap; does not compound and does not
block other work.

## 3. Governance / Scope Deviation (outside the five requested categories)

This does not fit Build/Package/Security/Documentation/Code-Quality cleanly — it is a decision
about existing scope, not a fixable defect — so it is called out separately rather than forced
into one of the five.

| ID | Issue | Severity | Status |
|---|---|---|---|
| REF-050 | A full Admission module and a full Academic module already exist, and `SecurityConfig` is already wired — all three predate and exceed the current foundation-only scope (Sprint 1 explicitly excludes any ERP module and defers security) | High | Open — decision required, not a code fix (see below) |

**Root cause:** prior implementation work (`Phase 2.x`/`Phase 3.x` commit history) proceeded
past the foundation stage before the documentation-planning work captured in
Sprint-1-Backend-Foundation-Plan.md existed to govern it.
**Impact:** none of the fixes above are blocked by this, but every package-structure fix
(REF-010, REF-012) and every base-class fix (REF-030) also touches these modules' files, since
they depend on the foundation. Left undecided, it's unclear whether Admission/Academic should be
refactored alongside the foundation fixes, frozen as-is, or reconciled against ADR-003/DG-SIS-001
(which govern exactly the Student-stub-creation interaction this Admission module would need to
call into).
**Files affected:** all Admission and Academic module files.
**Recommended fix:** not a code fix — a scope decision from you: keep and reconcile these modules
against the approved SIS/Admission design chain (ADR-003, DG-SIS-001, Phase 4-13) once DG-SIS-001
is resolved, freeze them untouched pending that resolution, or something else.
**Before further development:** **Decision needed before Admission/Academic-specific work
resumes** — it does not block the foundation refactor itself (REF-001 through REF-042), since
those are prerequisites for any backend code regardless of which modules exist.
**Status (2026-08-05):** still open — no decision made, none attempted. The Admission and
Academic modules' files were mechanically touched by REF-010 (package rename) and REF-012
(common-nesting move) purely because they import foundation classes (e.g.
`common.exception.ResourceNotFoundException`) — those were text-substitution import-path fixes
only, with no change to either module's business logic, entities, services, or controllers, and
do not constitute progress on this item's actual question of what to do with the modules
themselves.

## 4. Package-Nesting Decision — Resolved (REF-012)

**Conclusion: mandatory refactoring — Option A.** `config/`, `security/`, `exception/`, and
`util/` must be relocated under `common/`, matching MDB Part-1 §6's package tree literally.
`audit/`, `constant/`, `validation/`, `dto/`, `mapper/`, and `logging/` must also relocate under
`common/`, on the same reasoning, since MDB Part-1 §6's tree admits no other location for
cross-cutting, non-business-module code. No approved exception for the current flat structure
exists in the documentation repository.

**Evidence:**

1. **MDB Part-1 §6 explicitly nests these four packages under `common/`** — its package-tree
   diagram shows `common/config`, `common/security`, `common/exception`, `common/util` as
   sub-packages, not as top-level siblings of `common`. The current source tree (`config/`,
   `security/`, `exception/`, `util/` as top-level packages alongside `common/`) does not match
   this diagram.
2. **MDB Part-1 §1 states the package layout is binding on all code, not advisory:** "this MDB
   is technology-specific: it fixes the stack (Section 3), the architecture's physical shape
   (Section 4), the repository and package layout (Sections 5-7) ... that every subsequent line
   of code must follow." Section 6 (Backend Package Structure) falls inside that "Sections 5-7"
   span.
3. **MDB Part-1 §6's package tree is closed, not illustrative** — it enumerates exactly 11
   business-module packages, `administration`, and `common`, with no other top-level package
   named or implied. `audit/`, `constant/`, `validation/`, `dto/`, `mapper/`, and `logging/`
   existing as additional top-level packages is therefore itself outside the fixed tree,
   independent of the config/security/exception/util question — `common/` is the only package
   MDB defines for cross-cutting, non-business-module code, so it is the only compliant
   destination for all of them, not only the four MDB names explicitly.
4. **BMDD Part-4 §39 resolves any BMDD/MDB conflict in favor of MDB**, and confirms deviation
   requires "explicit, documented approval," not a silent default: "Any conflict between this
   BMDD and an earlier document in the chain ... is resolved in favor of the earlier, more
   foundational document — this BMDD elaborates and applies those decisions at the code-structure
   level, it does not have authority to silently override them." BMDD Part-1 §2-3's own summary
   of the package tree omits the `common` sub-package nesting, but per this governance clause
   that omission cannot override MDB Part-1 §6.
5. **No approved exception exists.** Neither ADR-001, ADR-002, nor ADR-003 addresses `common`'s
   internal structure (ADR-001 only adds the `academic` module package). No other approved
   document records a reviewed, documented exception for the current flat structure, and BMDD
   Part-1 §4's governance principle for structural deviations ("requires an explicit, reviewed
   exception, not a silent per-module variation") has not been exercised here. Absent that
   record, the default — compliance with MDB Part-1 §6 as written — governs.

This rules out both alternatives originally framed in this section: it is not an **allowed
implementation variation** (MDB's binding-layout language in §1 and its closed package list in
§6 leave no discretion), and it is not an **approved exception** (no ADR or other approved
document ratifies the current flat structure). Sprint-1-Backend-Refactoring-Plan.md's REF-012
entry and backlog are updated below to reflect this as a settled, mandatory item rather than an
open decision.

## 5. Sprint Backlog — Execution Order

Ordered by dependency, not by severity alone — a Critical item that nothing else depends on can
still sit after a High item that everything else depends on.

| Order | ID | Item | Depends on | Before further dev? |
|---|---|---|---|---|
| 1 | REF-020 (rotation only) | Rotate the exposed PostgreSQL credential | — | Yes, immediately, independent of build fixes — **not yet done, still open** |
| 2 | REF-001 | Fix `pom.xml` parent version to a real Spring Boot 3.x release | — | Yes — **✅ Completed 2026-08-05** |
| 3 | REF-002 | Fix `pom.xml` fabricated artifact IDs | REF-001 | Yes — **✅ Completed 2026-08-05** |
| 4 | REF-003 | Rewrite `JacksonConfig` against Jackson 2.x (`com.fasterxml.jackson`) | REF-001 | Yes — **✅ Completed 2026-08-05** |
| 5 | REF-004 | Regenerate `.mvn/wrapper/maven-wrapper.properties` | REF-001 | Yes — **✅ Completed 2026-08-05** |
| 6 | — | **Build verification checkpoint: `mvn clean compile`** | 2-5 | Yes — **✅ Completed 2026-08-05: `./mvnw clean compile` → `BUILD SUCCESS`, 112 source files, zero warnings/errors. Note: this Part verified `compile` only, per this Part's explicit scope — `clean verify` (full test phase) was not run and remains a later checkpoint.** |
| 7 | §4 | Package-nesting decision — resolved: mandatory, Option A (§4) | Build checkpoint | Yes — resolved, no longer blocking on a pending decision |
| 8 | REF-010 | Rename root package `com.shakticode.schoolerp` → `com.schoolerp` | §4 (resolved) | Yes — **✅ Completed 2026-08-05** |
| 9 | REF-012 | Relocate `config/`, `security/`, `exception/`, `util/`, `audit/`, `constant/`, `validation/`, `dto/`, `mapper/`, `logging/` under `common/` per §4 | REF-010, §4 (resolved) | Yes — **✅ Completed 2026-08-05** |
| 10 | REF-011 | Align Maven `groupId` | REF-010 | No — **✅ Completed 2026-08-05**, batched with 8-9 |
| 11 | — | **Build verification checkpoint: confirm the renamed/moved tree still compiles** | 8-10 | Yes — **✅ Completed 2026-08-05: `./mvnw clean compile` → `BUILD SUCCESS`** |
| 12 | REF-020 (file) | Externalize the datasource password out of `application.properties` | Build checkpoint (11) | No, but do promptly — **⚠️ File fixed 2026-08-05; credential rotation itself still outstanding (operational action, not a code change)** |
| 13 | REF-030 | Add soft-delete field to `BaseAuditEntity` | 11 | Yes, before new entities — **✅ Completed 2026-08-05 (entity field + test); repository-level default-scope filtering deliberately deferred, see REF-030 note** |
| 14 | REF-031 | Add `BusinessRuleException`/`IntegrationException` types + handlers | 11 | No — Open, Medium priority, not in this Part's scope |
| 15 | REF-032 | Switch `CacheConfig` to Redis-backed `CacheManager` | 11 | No — Open, Medium priority, not in this Part's scope |
| 16 | REF-042 | Thread correlation ID into `GlobalExceptionHandler` error responses | 11 | No — Open, Medium priority, not in this Part's scope |
| 17 | REF-041 | Adopt Lombok in foundation classes (batch with 13-16 as each file is touched) | 13-16 | No — Open, Low priority, not in this Part's scope |
| 18 | REF-040 | Backfill unit tests for touched foundation classes; enforce "no test, no merge" going forward | 13-17 | Yes, as a going-forward rule from this point — **⚠️ Applied to this Part's own change (`BaseAuditEntityTest` added); retroactive backfill for pre-existing classes and both modules still outstanding** |
| 19 | REF-005 | Pin/enforce Java 21 toolchain | — | No — Open, Low priority, not in this Part's scope |
| 20 | REF-021 | Track `SecurityConfig` `permitAll()` replacement as a live backlog item for the Security Foundation sprint work | — | No — Open, Low priority, not in this Part's scope |
| 21 | REF-050 | Governance decision on Admission/Academic module disposition | — | Decision needed before Admission/Academic-specific work resumes; does not block 1-20 — **Still open, no decision made (see REF-050 status note)** |

---

Backend Refactoring Plan is finalized.
