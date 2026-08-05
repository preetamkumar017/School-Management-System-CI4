---
status: Accepted
date: 2026-08-05
deciders: Preetam Sinha
relates-to: ADR-002 (SIS ownership of Guardian/StudentGuardianLink); supersedes-in-spirit the Phase 4.2 `applicationId` note and the Phase 4.4 `CreateStudentRequest`/Phase 4.5 `StudentMapper.toEntity` shapes approved before this ADR, pending their formal revision
---

# ADR-003: Admission owns the Student stub-creation trigger; SIS owns the Student entity and its maintenance

## Context

Phase 4.6 (SIS Service Design) surfaced that the approved Phase 4.2 Domain Model and Phase 4.4 DTO Design assumed SIS independently owns and exposes `Student` creation (a `StudentService::createStudent()` operation, with `Sis` calling out to Admission's `ApplicationService` to validate the source `Application`) — this was recorded as **Option A** during evaluation and is not adopted; retained here only for historical traceability.

Documentation analysis found:

- **FR-02** ("Automatic Admission Number Generation," Appendix-E) is filed under Module "Admission & Enrollment," not SIS. Its Main Flow step 5 — "System creates the linked Student Master record stub (feeds FR-06)" — and its Data Created field ("Admission Number record; stub Student Master record") both place stub creation as an automatic, synchronous consequence of Admission's own "Confirm Enrollment" action, alongside Admission Number generation and the Application status change to Admitted (FR-02 §13 Post Conditions, §33 Success Scenario). §17 Related Business Rules is BR-ADM-002, an Admission rule.
- **FR-06** ("Comprehensive Student Profile," Appendix-E) never creates the stub — its Main Flow begins "Admin Staff opens the stub student record" (i.e., the stub already exists) and covers only completing/updating it and gating the Draft→Active transition (BR-SIS-003, BR-SIS-006).
- The Admission module's planned design contains no implementation of FR-02's Confirm Enrollment/stub-creation sequence (`ApplicationService::recordDecision()`'s ADMITTED path only calls `admitIntoSeatAllocation`) — this gap exists independent of anything in SIS.
- Company Development Standard cross-module rule (already governing ADR-001/ADR-002): *"A module may call another module's Service class, but never its Model/Entity directly"*; and dependency direction never cycles between two modules.
- Direct Service-to-Service dependency remains preferred over an event/queue pattern wherever a synchronous, immediate result is genuinely required. FR-02 §13/§33 describe the Admission Number and stub as issued together, synchronously, at confirmation.
- ADR-002's Consequences section already states Guardian/contact data, if ever needed by another module, would be "consumed by calling `Sis`'s own Service class" — establishing that `Sis` is expected to be called into.

## Decision

Ownership of `Student` stub creation is split along the same line FR-02 and FR-06 already draw:

**Admission owns the Confirm Enrollment trigger.** The orchestration sequence FR-02 describes — validate seat capacity/identity, generate the Admission Number, create the `Student` stub, transition the `Application` to Admitted — is triggered and sequenced from within Admission's Service layer, per FR-02 §3 (Module: Admission & Enrollment), §10 (Main Flow), and §17 (BR-ADM-002).

**SIS owns the `Student` entity, its persistence, and all post-creation maintenance.** Admission's Service layer calls a SIS Service class's public method to perform the actual `Student`-stub persistence step, per the Company Development Standard's cross-module rule (Service-to-Service only, never Model/Entity directly) — SIS's Models/Entities are never touched by Admission directly. Everything FR-06 describes (profile completion, BR-SIS-003/BR-SIS-006-gated Draft→Active activation, BR-SIS-005-governed section transfer) remains entirely within SIS's own Service layer, unaffected by this decision, consistent with ADR-002.

`Sis`'s dependency on Admission's `ApplicationService` (assumed under the unadopted Option A) is not adopted — SIS does not call into Admission's Service layer for this interaction.

## Consequences

- FR-02's currently-undesigned Confirm Enrollment sequence (Admission Number generation, seat/identity re-validation, `Student`-stub creation, Application status transition to Admitted) must be built in Admission's Service layer as part of realizing this decision — this gap exists independent of SIS and is not created by this ADR.
- A SIS Service method, narrower than the previously-drafted `StudentService::createStudent()`, is needed to accept the stub-creation call from Admission. Its exact shape is not designed by this ADR.
- Phase 4.2's `applicationId` note ("existence/ownership validated in the Service layer against Admission's Service class") no longer describes the accepted call direction and requires revision — not performed by this ADR.
- Phase 4.4's `CreateStudentRequest`, drafted as a client-facing validated request DTO for an independent SIS creation endpoint, no longer matches the accepted trigger ownership and requires reconsideration — not performed by this ADR.
- Phase 4.5's `StudentMapper`/entity-mapping logic for `CreateStudentRequest` is downstream of the Phase 4.4 outcome and requires revisiting once that DTO shape is settled — not performed by this ADR.
- Phase 4.3 (Repository/Model Design) is the least affected: an `existsByApplicationId`-equivalent Model method remains valid under this decision and should be reconfirmed only once Phase 4.2's field-level changes are finalized.
- Phase 4.6 (Service Design, previously paused) resumes under this decision once Phase 4.2/4.4/4.5 are revised — not performed by this ADR.
- The stub-field-nullability question (`fullName`, `dob`, `category`, `aadhaarNumber`, `sectionId` vs. FR-06's "stub... completed thereafter" model) remains open and is not resolved by this ADR — this decision settles *who* triggers and *who* persists stub creation, not *which fields* the stub carries at creation time.
