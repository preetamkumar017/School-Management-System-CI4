---
status: Final (Revision 1) — clarification request, not an architecture document
last-updated: 2026-08-05
audience: Requirement Owner / Business Analyst
references: DG-SIS-001, Phase 4.2 Revision 1, Phase 4.4 Revision 1, Phase 6 Revision 1, Phase 7 Revision 1, Phase 9 Revision 1, Phase 11 Revision 1
---

# Phase 12 — Requirement Clarification Package (DG-SIS-001), Revision 1

*Prepared for: Requirement Owner / Business Analyst. This is a clarification request, not an architecture or design document — it contains no proposed solutions.*

## 1. Background

While designing the Student Information System (SIS) module's data model, the design team found that two parts of the approved requirement documentation describe the student record's Class/Section field differently, and no document explains how to reconcile them. This was recorded as an open documentation gap, DG-SIS-001, and has remained unresolved through eleven subsequent design and review phases.

Documents reviewed in identifying and re-confirming this gap: FR-02 ("Automatic Admission Number Generation"), FR-06 ("Comprehensive Student Profile") — both from the Functional Requirements Catalogue — the Data Dictionary (Appendix-G) entry for the Student record, ADR-003 (the architecture decision governing who creates a student record and when), and the full SIS/Admission design set produced since (Phase 4.2 through Phase 11).

## 2. What is already known

- A new Student record is created automatically the moment Admission Staff confirms an admission ("Confirm Enrollment"), per FR-02.
- At that moment, the only documented outputs are an Admission Number and a link to the originating Application (FR-02 §21, "Data Created").
- Class/Section is documented as one of the fields checked for completeness before a student record can become "Active" — this check happens later, during a separate profile-completion step, performed by Admin Staff (FR-06 §6, §10, §16).
- Separately, the Data Dictionary (Appendix-G) lists Class/Section as a mandatory — i.e., always-required — field on the Student record, with no exception noted for a newly created record.
- Responsibility for triggering student-record creation belongs to the Admission team's "Confirm Enrollment" action; responsibility for maintaining the student record afterward (including completing the profile) belongs to the Student Information (SIS) team — an accepted architecture decision (ADR-003), not itself in question.
- No approved document — including all SIS/Admission design work produced since — states how or when Class/Section comes to be filled in between the record's creation and the completeness check.

## 3. What remains unknown

- **Unknown 1:** Whether Class/Section is known at the moment a student record is first created, or only becomes known later.
- **Unknown 2:** If Class/Section is not known at creation, who supplies it afterward and through what business process.
- **Unknown 3:** Whether a student record is allowed to exist, even temporarily, without a Class/Section — or whether one must always be present from the moment the record exists.
- **Unknown 4:** Whether the Data Dictionary's "always required" statement or the admission-workflow's "completed later" description is correct, or whether one of the two source documents needs correction.

## 4. Why architecture cannot proceed

- The Student record's data model definition for Class/Section (Phase 4.2 — Domain Model) cannot be finalized.
- The data-entry form design for creating a student record (Phase 4.4 — DTO Design) cannot determine whether Class/Section belongs on that form or a later one.
- The corresponding data-mapping logic (Phase 4.5 — Mapper Design) cannot be finalized.
- The "activate student" business logic (Phase 4.6 — Service Design) cannot finalize its completeness check.
- The corresponding screen/endpoint design (Phase 4.7 — Controller Design) cannot be finalized.
- The technical hand-off between the Admission and Student Information systems (Phase 7 — Published SIS Service Interface Design) cannot finalize what information is exchanged at student-record creation time.
- Per the Architecture Readiness Review (Phase 9), coding of the student creation and activation workflow cannot begin until this is resolved.

## 5. Clarification questions

| Question ID | Question | Why the answer is required | Which artifact depends on the answer |
|---|---|---|---|
| DG-SIS-001-Q1 | At the moment a new student record is first created (immediately after admission is confirmed), is the student's Class/Section already known, or is it decided later? | Determines whether Class/Section is captured at admission-confirmation time or during a later profile-completion step. | Phase 4.2, Phase 4.4, Phase 7 |
| DG-SIS-001-Q2 | If Class/Section is not known when the record is first created, who is responsible for supplying it afterward, and through what business process or screen? | Determines which role and which screen/action is responsible for completing this field, and when. | Phase 4.4, Phase 4.6 |
| DG-SIS-001-Q3 | Can a student record legitimately exist, even temporarily, without a Class/Section assigned — or must every student record always have one from the moment it is created? | Determines whether the field may be left blank temporarily, or must always hold a value. | Phase 4.2 |
| DG-SIS-001-Q4 | The Data Dictionary (Appendix-G) states Class/Section is mandatory on the Student record, but the admission workflow (FR-02) describes creating a "stub" student record before Class/Section is available. Which statement should take precedence, or should one source document be corrected? | Resolves a direct contradiction between two approved requirement documents. | Appendix-G, FR-02, FR-06 |

No suggested answers or implementation options are provided.

## 6. Expected documentation updates

- DG-SIS-001
- Phase 4.2 — Domain Model
- Phase 4.3 — Repository Design
- Phase 4.4 — DTO Design
- Phase 4.5 — Mapper Design
- Phase 4.6 — Service Design (SIS)
- Phase 4.7 — Controller Design
- Phase 4 Closure Report
- Phase 5 — Implementation Plan
- Phase 6 — Service Design (Admission)
- Phase 7 — Published SIS Service Interface Design
- Phase 8 — Admission ↔ SIS Interaction Sequence Design
- Phase 9 — Architecture Readiness Review
- Phase 10 — Architecture Resolution Planning
- Phase 11 — DG-SIS-001 Resolution Analysis
- Appendix-G (Data Dictionary) — only if clarification determines the source document itself requires correction

## Consistency review

Checked against the full approved baseline. Every statement in Sections 1–4 restates an already-approved fact or an already-recorded unresolved question; no new requirement, architecture, or resolution is introduced. No contradiction found.
