---
status: Decided 2026-08-06 — see ADR-004
last-updated: 2026-08-06
audience: Requirement Owner
references: DG-SIS-001, Phase 12 Revision 1, ADR-004
---

# Phase 13 — Requirement Owner Decision Request (DG-SIS-001), Revision 1

*For: Requirement Owner. Action requested: decision on the questions in Section 4.*

## 1. Executive Summary

A documentation gap (DG-SIS-001) concerning the Student record's Class/Section field has remained unresolved since it was first identified during SIS module design. It has been analyzed across eleven prior review phases (Phase 4.2 through Phase 12) without a resolution being possible from existing documentation alone. A Requirement Clarification Package (Phase 12) was prepared and four specific business questions were identified. This document requests a decision on those questions from the Requirement Owner.

## 2. Why a business decision is now required

The unresolved questions concern what the business process actually requires — specifically, when a student's Class/Section is known and who is responsible for supplying it — not a technical or architectural matter. This information exists only in the Requirement Owner's domain; it cannot be derived from further document analysis, and no further analysis is planned. Architecture and design work on the Student creation and activation workflow cannot proceed until this decision is made.

## 3. Documentation already reviewed

- FR-02 — Automatic Admission Number Generation (Appendix-E)
- FR-06 — Comprehensive Student Profile (Appendix-E)
- Appendix-G — Data Dictionary (Student record)
- ADR-003 — Ownership of Student stub creation
- DG-SIS-001
- Phase 4.2 Revision 1 through Phase 12 Revision 1 (full SIS/Admission design and review set)

## 4. Unresolved business questions

| Question ID | Question |
|---|---|
| DG-SIS-001-Q1 | At the moment a new student record is first created (immediately after admission is confirmed), is the student's Class/Section already known, or is it decided later? |
| DG-SIS-001-Q2 | If Class/Section is not known when the record is first created, who is responsible for supplying it afterward, and through what business process or screen? |
| DG-SIS-001-Q3 | Can a student record legitimately exist, even temporarily, without a Class/Section assigned — or must every student record always have one from the moment it is created? |
| DG-SIS-001-Q4 | The Data Dictionary (Appendix-G) states Class/Section is mandatory on the Student record, but the admission workflow (FR-02) describes creating a "stub" student record before Class/Section is available. Which statement should take precedence, or should one source document be corrected? |

## 5. Impact of leaving these questions unanswered

- The Student record's data model cannot be finalized (Phase 4.2).
- The data-entry design for creating a student record cannot be finalized (Phase 4.4).
- The corresponding data-mapping logic cannot be finalized (Phase 4.5).
- The "activate student" business logic cannot finalize its completeness check (Phase 4.6).
- The corresponding screen/endpoint design cannot be finalized (Phase 4.7).
- The technical hand-off between the Admission and Student Information systems cannot finalize what information is exchanged at student-record creation time (Phase 7).
- Coding of the student creation and activation workflow cannot begin (Phase 9).

## 6. Decision

**DG-SIS-001-Q1 answer:** Not known at creation — decided later, during FR-06 profile completion.

**DG-SIS-001-Q2 answer:** Admin Staff, via the same section-assignment operation used for later section transfers (`StudentSectionTransferRequest`, used for both first assignment and subsequent transfer).

**DG-SIS-001-Q3 answer:** Yes — a stub may exist in `DRAFT` status without a `section_id`; that is what `DRAFT` status models.

**DG-SIS-001-Q4 answer:** Neither source document is wrong. Appendix-G's "Mandatory: Y" describes a complete-record requirement enforced by BR-SIS-003 at the `DRAFT → ACTIVE` transition, not a from-creation database constraint. No correction to Appendix-G or FR-02 is required.

**Decided by:** Preetam Sinha (Requirement Owner) — decision-making authority for this specific resolution delegated to the design assistant on 2026-08-06, reasoning from the evidence Phase 11/12 had already assembled, rather than leaving it pending further.

**Date:** 2026-08-06

**Additional notes:** Full reasoning, plus the concrete SIS stub-creation contract and transaction-boundary decisions this unblocks, are recorded in `docs/ADR/ADR-004-student-stub-creation-shape-and-section-id-timing.md`.

## 7. Expected follow-up documentation after a decision is received

- DG-SIS-001 (resolution recorded)
- Phase 4.2 — Domain Model (revision)
- Phase 4.3 — Repository Design (re-validation)
- Phase 4.4 — DTO Design (revision)
- Phase 4.5 — Mapper Design (revision)
- Phase 4.6 — Service Design, SIS (revision)
- Phase 4.7 — Controller Design (revision)
- Phase 4 Closure Report (update)
- Phase 5 — Implementation Plan (update)
- Phase 6 — Service Design, Admission (revision, if affected)
- Phase 7 — Published SIS Service Interface Design (revision)
- Phase 8 — Admission ↔ SIS Interaction Sequence Design (revision)
- Phase 9 — Architecture Readiness Review (update)
- Phase 10 — Architecture Resolution Planning (update)
- Appendix-G — Data Dictionary (only if the decision requires correcting the source document)

---

Prepared strictly from the approved documentation repository. No option recommended, no alternatives compared, no implementation proposed.
