---
status: Final — Gap Analysis unchanged since first issue; Implementation Order revised
date: 2026-08-05
audience: Backend Architecture Team, Preetam Sinha
references: RGD/Appendix-E, Appendix-C, Appendix-G/H, Appendix-K §18.1, ADR-001/002/003, DG-SIS-001, Phase 4 Closure Report, Phase 5, Phase 6 Rev 1, Phase 7 Rev 1, Phase 8 Rev 1, Phase 9 Rev 1, BMDD Parts 1-4, ASD Parts 1-2, Sprint-1-Backend-Foundation-Plan.md, Sprint-1-Backend-Refactoring-Plan.md
---

# Sprint 2 — Admission Module Audit & Implementation Plan

Audited against: RGD/Appendix-E (FR-01–FR-05c, FR-02 Confirm Enrollment), Appendix-C (BR-ADM-001–008), Appendix-G/H (ENT-ADM-001/002), Appendix-K §18.1, ADR-001/002/003, DG-SIS-001, Phase 4 Closure Report, Phase 5, Phase 6 Rev 1, Phase 7 Rev 1, Phase 8 Rev 1, Phase 9 Rev 1, BMDD Parts 1–4, ASD Parts 1–2, Sprint-1-Backend-Foundation-Plan.md, Sprint-1-Backend-Refactoring-Plan.md, and the current `backend/src/main/java/com/schoolerp/admission/**` + `common/**` source.

**Process note:** `Sprint-1-Backend-Refactoring-Plan.md` still lists REF-031 (missing `BusinessRuleException`/`IntegrationException`) as "Open." It was actually resolved during Sprint 1 Part 3 — that Part's instructions scoped the doc update to `Sprint-1-Backend-Foundation-Plan.md` only, so the refactoring plan was never told. The **code** is treated as authoritative below, not that stale line.

**Correction notice (2026-08-05):** an architecture analysis, performed after Sprint 2 Part B's
initial implementation, found — from Appendix-G's ENT-SIS-001 (Student) Attribute Catalogue and
Phase 4.2 Revision 1 (SIS Domain Model), both already in the approved baseline — that
`admission_number` is a **Student** attribute, not an **Application** attribute. Part B had
added `admissionNumber` to `Application` (entity, DTO, mapper) and generated a value for it at
confirmation time; this was an unverified assumption contradicting both documents and has been
**reverted**. No new Documentation Gap was recorded — the persistence location was already
specified, just not consulted before implementing. Every row below marked "✅ Completed —
Sprint 2 Part B" for the Admission Number's *placement on Application* has been corrected
in place, with the correction visible inline rather than silently rewritten.

## 1. Gap Analysis

### Entity
| Item | Status |
|---|---|
| `Application` (ENT-ADM-001) — fields, `BaseAuditEntity` inheritance (incl. soft-delete), immutable `academicSessionId` | **1. Already correct** |
| `SeatAllocation` (ENT-ADM-002) — composite unique key, capacity/RTE counters | **1. Already correct** |
| `classAppliedId`/`classId`/`academicSessionId` as plain `Long` FKs (no `@ManyToOne`) | **1. Already correct** — explicitly documented as deferred to Service-layer validation via Academic's published interface, matching BMDD Part-1 §6's no-cross-module-Entity-dependency rule |
| Soft-delete field usage (e.g. a "withdraw application" path) | **3. Missing** — infrastructure exists (`deleted`/`deletedAt`), no Service/Controller method sets it. Not clearly a gap: no Appendix-K/FR statement found requiring a withdraw operation was located in the reviewed set — needs confirmation against Appendix-K before treating as a requirement |

### Repository
| Item | Status |
|---|---|
| `ApplicationRepository` — CRUD, lookup by reference no/Aadhaar, `JpaSpecificationExecutor` for SCR-ADM-002 filters | **1. Already correct** |
| `SeatAllocationRepository` — CRUD, pessimistic-lock variant for the admit path | **1. Already correct**, matches BMDD Part-2 §17's named safety-critical exception |

### DTO
| Item | Status |
|---|---|
| `CreateApplicationRequest`/`UpdateApplicationRequest` — Bean Validation, PII (`aadhaarNumber`) correctly excluded from responses | **1. Already correct** |
| `ApplicationResponse`/`ApplicationSummaryResponse`/`SeatAllocationResponse`/`ApplicationSearchRequest`/`ApplicationDecisionRequest`/`SeatAllocationRequest` | **1. Already correct** |
| An "Admission Number" field/response shape (FR-02) | **⚠️ Reverted — Sprint 2 Part B correction, 2026-08-05.** Originally added as `ApplicationResponse.admissionNumber`; found inconsistent with Appendix-G (which places `admission_number` on `Student`, ENT-SIS-001, not `Application`) and removed. **4. Blocked** — same as FR-02 Step 5, pending DG-SIS-001/Student. |
| A `CreateStudentRequest`-adjacent DTO for the SIS call | **4. Blocked** — ADR-003 Consequences explicitly states this DTO's shape is unresolved; Phase 4.4 marks it Open |

### Mapper
| Item | Status |
|---|---|
| `ApplicationMapper`/`SeatAllocationMapper` — explicit field-by-field, no reflective copy | **1. Already correct** |

### Service
| Item | Status |
|---|---|
| `submitApplication` — duplicate check (BR-ADM-006 partial), Academic cross-module validation, reference-no generation, session resolution | **1. Already correct** for what it does |
| Age-appropriateness check, Aadhaar checksum validity (both explicitly named in `CreateApplicationRequest`'s own Javadoc as Service-layer responsibilities) | **Split outcome — Sprint 2 Part B, 2026-08-05.** Aadhaar checksum: **✅ Completed** — Verhoeff algorithm (UIDAI's standard check-digit scheme), verified against the canonical reference test vector. Age-appropriateness: **still 3. Missing, and now confirmed non-fabricable** — full primary-source review (Appendix-C, Appendix-E FR-01 §16, `AcademicClass` entity) found no numeric age band anywhere, per-class or general; FR-01 §16 states only "within an age-appropriate range for the class applied" descriptively. Implementing a specific numeric check would mean inventing unapproved business policy. Flagged as a genuine data/policy gap (Client/Product decision on age bands, or an Academic-module schema addition — both out of this Part's scope), not a technical blocker. |
| `updateApplication` — editable-status gate, BR-ADM-004 reclassification lock | **1. Already correct** |
| `recordDecision`/`admitIntoSeatAllocation` — BR-ADM-001/003 re-validation under pessimistic lock, forward-only status | **1. Already correct** |
| BR-ADM-002 (Admission Number uniqueness) | **⚠️ Corrected — Sprint 2 Part B correction, 2026-08-05.** Primary-source review of Appendix-C found BR-ADM-002 is actually titled "Single Admission Number per Student" — an identity-deduplication rule against **active student records** — consistent with `admission_number` living on `Student` (Appendix-G ENT-SIS-001), not `Application`. The number-uniqueness mechanism (`ReferenceSequence`'s pessimistic-locked counter, DB-level `unique` constraint) previously credited here was scoped to the now-reverted `Application.admissionNumber` column and no longer applies. **4. Blocked**, same as FR-02 Step 5 — nothing about BR-ADM-002 is enforceable until `Student` exists. The one part of this row that remains genuinely implemented and unaffected: `validateNoDuplicate` re-run at confirmation time (BR-ADM-006's own identity check, re-validated per FR-02 step 3) — that stays, see Service section. |
| BR-ADM-005 (block confirmation on missing verified documents) | **3. Missing**, and **dependent on** `administration`'s `DocumentService` (not built) — not an ADR/DG block, a Sprint-sequencing dependency |
| BR-ADM-007 (seat-hold expiry) | **3. Missing** — no "hold" concept exists on `SeatAllocation` at all; dependent on Background Job infrastructure, which Sprint-1-Backend-Foundation-Plan.md §3 explicitly deferred out of Sprint 1 |
| BR-ADM-008 (waitlist ranking/offer engine) | **3. Missing** — no ranking logic exists; no approved detailed design for the algorithm itself was found in the reviewed document set (distinct from being "blocked" — nothing to implement against yet) |
| FR-02 step 4 — generate Admission Number at confirmation | **⚠️ Reverted — Sprint 2 Part B correction, 2026-08-05.** The generation call (`AdmissionNumberGenerator`/`AdmissionNumberGeneratorImpl`, `ReferenceNumberType.ADMISSION_NUMBER`) was removed along with the `Application.admissionNumber` field it fed — a generator with nowhere documentation-correct to persist its result would only recreate the same inconsistency. **4. Blocked** — same as FR-02 Step 5, by the existing approved architecture (Student cannot be built until DG-SIS-001 resolves), not a new documentation gap. |
| FR-02 step 5 — trigger `Student` stub creation via SIS | **4. Blocked, unchanged.** Phase 9 §B1: the callee (SIS's concrete published Service interface) "cannot be finalized" until DG-SIS-001 resolves; ADR-003 confirms Admission owns the trigger but the interface it would call doesn't exist. **Explicitly and deliberately not implemented in Part B** — confirmed by direct code inspection: no SIS package reference exists anywhere in `admission/**`. |
| Whether "Confirm Enrollment" is a new operation or an extension of `recordDecision`'s `ADMITTED` path | **✅ Decided — Sprint 2 Part B, 2026-08-05: extension, not a new operation.** See §3 revision note below for the reasoning (Phase 6 Revision 1's own preliminary finding, plus this Part's "do not modify REST APIs unless compilation requires it" constraint, which a new endpoint would have violated). |
| Transaction boundary / compensating behavior for the Admission→SIS call | **Open, untracked** (Phase 9 §B2) — in practice cannot be finalized before the SIS interface (item above) exists |
| `AuditLogService` call (BMDD Part-2 §13's validate→enforce→persist→**audit**→event sequence) | **3. Missing**, self-acknowledged; **dependent on** `administration` package (not built) |
| Migration from message-text-embedded rule IDs to the new `BusinessRuleException(ruleId, message)` | **✅ Completed — Sprint 2 Part A, 2026-08-05.** BR-ADM-001/003/004/006 now throw `BusinessRuleException(ruleId, message)` in `ApplicationServiceImpl`/`SeatAllocationServiceImpl`. |

### Controller
| Item | Status |
|---|---| 
| `ApplicationController`/`SeatAllocationController` — one Controller method per operation, delegates to exactly one Service method, correct HTTP methods/status codes, `/api/v1/...` versioning, `ApiResponse`/`PageMetadata` wrapping | **1. Already correct** |
| Authorization annotations (BMDD Part-1 §14: "never left without an explicit authorization declaration") | **3. Missing** on every endpoint — **dependent on Security Foundation** (Sprint-1-Backend-Foundation-Plan.md §2.7, explicitly deferred), not an ADR/DG block |
| Idempotency-key handling on the decision/confirmation endpoint (ASD Part-1 §4 explicitly names *"Admission confirmation"* as idempotency-sensitive) | **3. Missing**, and **no shared foundation piece exists yet either** — this isn't just a module gap, it's an unbuilt common-infrastructure capability |

### Validation
| Item | Status |
|---|---|
| Bean Validation on all Request DTOs | **1. Already correct** |
| Cross-field checks (`submittedFrom <= submittedTo`, RTE ceiling math, forward-only status) | **1. Already correct**, implemented as named Service-layer steps per BMDD Part-2 §15 |
| Using `common.validation.ValidationUtils` (built in Sprint 1 Part 3) for these checks | **✅ Completed — Sprint 2 Part A, 2026-08-05.** `searchApplications`'s date-range check and `recordDecision`'s remarks-required check now delegate to `ValidationUtils.requireTrue`/`requireNonBlank`; same `ValidationException` type/messages. |

### Exception Handling
| Item | Status |
|---|---|
| Routing through the centralized `GlobalExceptionHandler` | **1. Already correct** |
| Business Rule Exception category usage | **✅ Completed — Sprint 2 Part A, 2026-08-05** (see Service section — same finding) |

### Integration Points
| Item | Status |
|---|---|
| Academic module (`AcademicSessionService` bridge interface, `AcademicClassService.getClass`) | **1. Already correct** — clean published-interface pattern per ADR-001/BMDD Part-1 §6 |
| SIS module (Student stub creation) | **4. Blocked** — DG-SIS-001 / ADR-003 chain (see Service) |
| `administration` package (`AuditLogService`, `DocumentService`) | **3. Missing**, dependent on unstarted work, not ADR/DG-blocked |
| Security | **3. Missing**, dependent on deferred Security Foundation |
| `common.generator` (reference numbers) | **1. Already correct** for `APPLICATION`. `ReferenceNumberType.ADMISSION_NUMBER` **✅ added — Sprint 2 Part A, 2026-08-05** (scheme/enum only); actual generation usage at confirmation time remains **3. Missing**, deliberately out of Part A's scope (see Service) |

### Tests
| Item | Status |
|---|---|
| Unit tests for any Admission class | **3. Missing completely** — zero test files exist for the entire module, confirmed by direct `find`. Matches Sprint-1-Backend-Refactoring-Plan.md REF-040 (High, explicitly still open for pre-existing module code). **5. Can be implemented immediately** for everything already built — see §3 for *when* within Sprint 2, revised below |

## 2. Dependency Graph

```
                    ┌─────────────────────────┐
                    │ DG-SIS-001 resolution    │  (Requirement Owner decision — Phase 13,
                    │ (external, not Sprint 2) │   outside engineering's control)
                    └────────────┬─────────────┘
                                 │ gates
                                 v
                    ┌─────────────────────────────┐
                    │ Concrete SIS published        │  (ADR-003 consequence — a future
                    │ Service interface design      │   Service Design phase beyond Phase 7)
                    └────────────┬─────────────────┘
                                 │ gates
                                 v
        ┌────────────────────────────────────────────┐
        │ FR-02 step 5: Student-stub creation call     │◄── genuinely blocked, Sprint 2 cannot
        │ from Admission (new code, not yet written)   │    complete this regardless of effort
        └───────────────────────┬──────────────────────┘
                                 │ (independent of the branch below)
                                 v
        ┌────────────────────────────────────────────┐
        │ Transaction boundary / compensating-behavior │◄── practically blocked by the same
        │ design for the Admission→SIS call            │    dependency, though not itself
        └────────────────────────────────────────────┘     ADR/DG-tracked

──────────────────────────────────────────────────────────────────────────
Everything below is independent of DG-SIS-001 and can proceed in Sprint 2:
──────────────────────────────────────────────────────────────────────────

  Migrate BR-ADM-001/003/004/006 to BusinessRuleException
  Adopt ValidationUtils for existing cross-field checks        (independent of each other,
  Add ReferenceNumberType.ADMISSION_NUMBER (common/generator)   all mechanical/additive)
        │
        v
  Add age-appropriateness + Aadhaar checksum checks to submitApplication
        │
        v
  Wire Admission Number generation into recordDecision's ADMITTED path
  (or a new operation — the "new vs. extend" judgment call, Open-but-untracked,
   made locally, no external dependency)
        │
        v
  Backfill unit tests for all now-finalized Admission functionality
  (deliberately last — see §3 revision note)

  Security Foundation (§2.7) ──► Authorization annotations on Controllers
  (external Sprint dependency, not Admission-module work itself)

  Idempotency-key common infrastructure ──► Wire into decision/confirm endpoint
  (external Sprint dependency — doesn't exist anywhere yet)

  administration package (AuditLogService, DocumentService) ──► BR-ADM-005, audit step
  (external Sprint dependency, not started)

  Background Job infrastructure ──► BR-ADM-007 seat-hold expiry
  (external Sprint dependency, deferred by Sprint-1-Backend-Foundation-Plan.md §3)

  No dependency found for BR-ADM-008 (waitlist ranking) — needs a design decision,
  not an engineering blocker
```

## 3. Safe Implementation Order — Revised

**Revision note (2026-08-05):** the order below moves unit-test backfill to *last*,
reversing this document's first issue (tests first). Reasoning: Sprint-1-Backend-Refactoring-Plan.md's
REF-040 resolution — already applied twice in this project (Sprint 1 Parts 2 and 3) — establishes
"test each class as it's touched for its own fix," not a separate test-everything-first pass.
Testing today's `ConflictException`-with-message-text behavior before migrating to
`BusinessRuleException` (already planned in this same sprint) would write assertions immediately
invalidated by that migration; testing `submitApplication` before its age/Aadhaar checks exist
means testing incomplete behavior that needs a second pass anyway. Neither BMDD Part-4 §33 nor
MDB Part-1 §10.3 requires tests to be written before other work — both fix a merge gate ("no code
merged without tests for the changed module"), which the revised order still satisfies, since
nothing here is proposed to merge before its test exists.

1. **Migrate BR-ADM-001/003/004/006 to `BusinessRuleException`** — mechanical, same control flow. **✅ Completed 2026-08-05.**
2. **Adopt `ValidationUtils` for existing cross-field checks** — cosmetic, same behavior; do alongside #1 since both touch the same methods. **✅ Completed 2026-08-05.**
3. **Add `ReferenceNumberType.ADMISSION_NUMBER`** — additive, no existing behavior touched. **✅ Completed 2026-08-05.**
4. **Implement age-appropriateness + Aadhaar checksum validation** in `submitApplication` — additive, self-contained. **✅ Aadhaar checksum completed 2026-08-05; age-appropriateness confirmed non-fabricable, see §1.**
5. **Decide and implement "Admission Number generation at confirmation"** (new operation vs. extending `recordDecision`) — the one local judgment call in this list; make the decision, then wire step 3's generator into it. **✅ Completed 2026-08-05 — decided: extend `recordDecision`.**
6. **Backfill unit tests for all now-finalized Admission functionality** (Entity, Repository, Mapper, Service, Controller) — written once, against the final shape produced by steps 1–5, not against an interim state.
7. **Everything past this point is blocked or dependent on other sprints' work** and is not safely startable within Sprint 2 on its own: SIS stub-creation call (DG-SIS-001), Security/authorization, idempotency-key handling, `AuditLogService`/`DocumentService` integration, BR-ADM-007 (Background Jobs), BR-ADM-008 (no design exists).

## 4. Sprint Breakdown — Revised

**Sprint 2, Part A — Exception/validation hygiene + Admission Number mechanism. ✅ Completed 2026-08-05.**
Items 1–3 above: `BusinessRuleException` migration, `ValidationUtils` adoption, `ADMISSION_NUMBER`
type. Small, mechanical, additive. No test-writing yet — the shape these touch is not yet final
(Part B changes the same methods further).

**Part A resolution:**
- `common/generator/ReferenceNumberType.java` — added `ADMISSION_NUMBER("ADM", true, 6)`.
  Prefix `"ADM"`/6-digit padding follows the same `<PREFIX>-<YEAR>-<SEQUENCE>` shape as
  `APPLICATION`'s `"APP"`; no document reviewed fixes the exact prefix string, so this is a
  documented engineering choice, consistent with the pattern already established for
  `PaginationConstants` in Sprint 1 Part 3. Class Javadoc updated to reflect the new state
  (`INVOICE`/`EMPLOYEE_CODE` still not added). No generator/Service usage added — the type exists,
  nothing calls it yet, per this Part's explicit "do not implement Admission Number generation
  yet" rule. `APPLICATION`'s own row/format is untouched (separate `ReferenceSequence` row per
  type, purely data-driven `format()` — confirmed by inspection and by the full test suite still
  passing).
- `admission/service/ApplicationServiceImpl.java` — BR-ADM-001, BR-ADM-003 (`admitIntoSeatAllocation`),
  BR-ADM-004 (`updateApplication`), BR-ADM-006 (`validateNoDuplicate`) now throw
  `common.exception.BusinessRuleException(ruleId, message)` instead of `ConflictException` with
  the rule ID embedded in the message text. The `submittedFrom`/`submittedTo` range check
  (`searchApplications`) and the `remarks`-required-when-`REJECTED` check (`recordDecision`) now
  delegate to `common.validation.ValidationUtils.requireTrue`/`requireNonBlank` — same
  `ValidationException` type, same messages, zero behavior change (confirmed
  `!isAfter(...)` was used, not `ValidationUtils.requireBefore`, since that helper's strict `<`
  semantics would have rejected equal timestamps the original logic allowed — that would have been
  an unauthorized behavior change). Class-level Javadoc updated to describe the new exception
  mapping. Generic, non-BR-numbered conflicts (`application no longer editable`, `status is
  forward-only`) intentionally left as `ConflictException`, out of this item's scope.
- `admission/service/SeatAllocationServiceImpl.java` — BR-ADM-003 (`validateRteCeiling`) migrated
  the same way. The uniqueness/capacity-reduction guards in `createSeatAllocation`/
  `updateSeatAllocation` are not BR-numbered in source and were left untouched.

**Verified:**
- `./mvnw clean compile` → `BUILD SUCCESS`, 118 source files.
- `./mvnw test` → 27/28 unit tests pass. The 1 failure, `BackendApplicationTests.contextLoads`
  (a full `@SpringBootTest`), is a pre-existing environmental limitation unrelated to this Part —
  traced to Hibernate being unable to resolve a JDBC dialect because no PostgreSQL instance is
  reachable in this sandbox (`DB_PASSWORD` has no default since Sprint 1 Part 2's REF-020, and no
  local database is running either way). None of the classes this test's failure trace touches
  (`HibernateJpaConfiguration`, JDBC dialect resolution) were modified by this Part.
- The only externally-visible behavior change is the one this item authorized: BR-ADM-001/003/004/006
  now respond with the `BusinessRuleException` → HTTP 422 mapping (Sprint 1 Part 3's
  `GlobalExceptionHandler`) instead of `ConflictException` → HTTP 409. This is the intended effect
  of "migrate to `BusinessRuleException`," not an incidental deviation.
- No Controller, DTO, Repository, Entity, or API contract path/method was modified.

**Sprint 2, Part B — New Service-layer functionality. ⚠️ Completed 2026-08-05, corrected same day.**
Items 4–5: age-appropriateness + Aadhaar checksum checks; the Admission-Number-at-confirmation
decision + wiring. Requires settling the "new operation vs. extend `recordDecision`" question
first — a design call, not a technical blocker.

**Part B resolution (original):**
- **Decision: extend `recordDecision`'s `ADMITTED` path, not a new operation/endpoint.**
  Grounded in Phase 6 Revision 1's own preliminary finding — FR-02's Confirm Enrollment
  "transitions status to Admitted and already enforces BR-ADM-001/BR-ADM-003... exactly what
  existing Admission implementation already does today when recording an Admitted decision" —
  and decisively confirmed by this Part's "do not modify REST APIs unless compilation requires
  it" rule, which a new Controller endpoint would have violated for no compilation reason. Zero
  Controller or endpoint changes were made. **This decision stands, unaffected by the correction
  below.**
- `admission/service/ApplicationServiceImpl.java` — `admitIntoSeatAllocation` renamed to
  `confirmEnrollment` and extended: (1) re-validate identity (`validateNoDuplicate`, now re-run
  at confirmation against every *other* application — its signature gained an
  `excludeApplicationId` parameter so the application being confirmed, already persisted and
  active, doesn't flag itself as its own duplicate); (2) re-validate seat capacity/RTE ceiling
  (unchanged from Part A); (3) *[reverted, see below]* generate and assign the Admission Number.
  `submitApplication` gained a Verhoeff-algorithm Aadhaar checksum check ahead of the existing
  duplicate check. **Items (1), (2), and the Aadhaar checksum check are unaffected by the
  correction and remain implemented.**
- Age-appropriateness: confirmed non-implementable without fabricating policy — see §1.
  **Unaffected by the correction.**

**Correction (2026-08-05, same day):** a follow-up architecture analysis found, from Appendix-G's
ENT-SIS-001 (Student) Attribute Catalogue and Phase 4.2 Revision 1 (SIS Domain Model) — both
already in the approved baseline, neither newly consulted before the original Part B work — that
`admission_number` is a **Student** attribute, not an **Application** attribute. The following
were reverted:
- `admission/entity/Application.java` — the added `admissionNumber` column removed; class
  Javadoc now explicitly documents why it does not belong here, to prevent recurrence.
- `admission/dto/ApplicationResponse.java` + `admission/mapper/ApplicationMapper.java` — the
  field and its mapping removed.
- `admission/service/ApplicationServiceImpl.java` — the generation-and-assignment call removed
  from `confirmEnrollment`; its Javadoc (class- and method-level) corrected to describe FR-02
  Steps 1–3 as implemented and Step 4 as blocked, alongside Step 5, by Student not yet existing
  (DG-SIS-001) — not by a new documentation gap.
- `common/generator/AdmissionNumberGenerator.java` + `AdmissionNumberGeneratorImpl.java` —
  deleted. A generator with no documentation-correct place to persist its result would only
  invite the same mistake again.
- `common/generator/ReferenceNumberType.java` — `ADMISSION_NUMBER` constant removed; Javadoc
  reverted to (and extended past) its pre-Part-B wording, now explaining *why* it's absent, not
  just that it is.

**Verified (post-correction):**
- `./mvnw clean compile` → `BUILD SUCCESS`, 118 source files (back down from 120 — the two
  deleted generator files).
- `./mvnw test` → 27/28 pass, identical pre-existing `BackendApplicationTests.contextLoads`
  environmental failure as Part A/original Part B — no new failures, no change in pass/fail
  pattern.
- The Verhoeff checksum implementation (unaffected by the correction) was verified against the
  standard reference test vector ("2363" valid; single-digit and adjacent-transposition
  alterations correctly rejected) before being trusted in this code.
- FR-02 Steps 1–3 implemented in `confirmEnrollment`; Steps 4 and 5 both confirmed absent by
  direct inspection — no `admissionNumber`/`admission_number`/`AdmissionNumberGenerator`
  reference remains anywhere except explanatory Javadoc, and no SIS package reference exists
  anywhere under `admission/**`.
- `ReferenceNumberType.APPLICATION` and `ApplicationReferenceGenerator`/`Impl` untouched by
  either the original Part B work or this correction.
- No Controller was modified, in either the original work or the correction.

**Sprint 2, Part C — Unit test backfill for all finalized functionality.**
Item 6: tests for Entity, Repository, Mapper, Service, Controller, written once against the
shape Parts A+B produced — no rewritten assertions, no incomplete-behavior coverage. Exit:
`./mvnw clean compile` and `./mvnw test` both green; every existing/changed class covered per
Appendix-M §8 / BMDD Part-4 §33.

**Not scheduled in Sprint 2 (blocked or cross-sprint dependent):** SIS stub-creation call,
transaction-boundary/compensating-behavior design, Security/authorization on Controllers,
idempotency-key infrastructure, `AuditLogService`/`DocumentService` integration, BR-ADM-007,
BR-ADM-008.

## 5. Exit Criteria for Sprint 2

- ✅ BR-ADM-001/003/004/006 raise `BusinessRuleException` with the correct rule ID, not message-text-embedded IDs. (Part A)
- ✅ Aadhaar checksum validity is enforced in `submitApplication` (Verhoeff algorithm, verified against the standard reference test vector). (Part B)
- ⚠️ Age-appropriateness is **not** enforced — confirmed, after full primary-source review, that no approved document or data model defines a numeric age band; implementing one would mean inventing unapproved policy. Tracked as a genuine data/policy gap (Client/Product decision or Academic-module schema addition), not left silently undone. (Part B)
- ⚠️ `ADMISSION_NUMBER` reference-number generation was implemented, found inconsistent with Appendix-G (admission number belongs to `Student`, not `Application`), and **reverted** (Part B correction, 2026-08-05). The confirmation-path decision itself — extend `recordDecision`'s `ADMITTED` path, not a new operation — stands and is unaffected. (Part B)
- ⚠️ FR-02 steps 1–3 (re-validate capacity/RTE, re-validate identity, transition to `Admitted`) are fully realized in `ApplicationServiceImpl.confirmEnrollment`. **Step 4 (generate Admission Number) is explicitly not implemented**, corrected from an earlier, documentation-inconsistent attempt — blocked by the existing approved architecture (Student cannot be built until DG-SIS-001 resolves), not by a new documentation gap. (Part B)
- Every existing/changed Admission class (Entity, Repository, Mapper, Service, Controller) has Unit Test coverage per Appendix-M §8/BMDD Part-4 §33, written against Sprint 2's final shape; `./mvnw clean compile` and `./mvnw test` both green.
- FR-02 steps 4-5 (Admission Number generation, SIS Student-stub creation) remain explicitly **not** implemented and explicitly **not** claimed as done — both gated on DG-SIS-001 and the concrete SIS Service interface design, exactly as Phase 9 §C's hard criteria state.
- No Security, idempotency, `AuditLogService`, `DocumentService`, or Background-Job work is claimed as complete — all remain correctly tracked as dependent on other, not-yet-started Sprint work.
