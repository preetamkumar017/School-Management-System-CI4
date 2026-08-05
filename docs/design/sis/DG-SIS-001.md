---
status: Resolved — see ADR-004
date: 2026-08-05
resolved-date: 2026-08-06
---

# DG-SIS-001 — Undocumented timing/mechanism for `Student.sectionId` population between stub creation (FR-02) and Draft→Active completeness check (FR-06)

## Source documents involved

- Appendix-E, FR-02 ("Automatic Admission Number Generation")
- Appendix-E, FR-06 ("Comprehensive Student Profile")
- Appendix-G, Data Dictionary — ENT-SIS-001 (Student), Attribute Catalogue, `section_id`
- ADR-003 (Ownership of Student Stub Creation)

## Confirmed facts

- FR-02 §10/§21 documents that a `Student` stub is created during Admission's Confirm Enrollment action, linked to the `Application`, alongside a newly-generated Admission Number. FR-02 itemizes its own output as "Admission Number record; stub Student Master record" and names nothing else as stub content.
- FR-06 §16 documents "Class/Section" as one of the fields whose completeness is checked (BR-SIS-003) before the `Student` record transitions from Draft to Active. This check occurs within FR-06's flow, which begins by opening the already-existing stub.
- Appendix-G's Student Attribute Catalogue marks `section_id`'s `Null` column as `N` (not nullable).
- ADR-003 establishes that Admission owns the stub-creation trigger and SIS owns the `Student` entity and its post-creation maintenance, but explicitly leaves the stub-field-nullability question unresolved.

## What is missing from the documentation

- No reviewed document describes how or when `sectionId` is populated between stub creation (FR-02) and the FR-06 completeness check.
- No reviewed document reconciles Appendix-G's `Null: N` marking for `section_id` against FR-02's stub-only output list.
- No reviewed document states whether `section_id` is present, absent, or held via any other means on a freshly created stub.

## Impact on Phase 4

Requires an architectural decision, supported by updated project documentation, before Phase 4.2, Phase 4.4, and Phase 4.6 can be finalized.

Until this documentation gap is resolved:
- Phase 4.2 cannot finalize the creation-time definition of `sectionId`.
- Phase 4.4 cannot finalize the DTO ownership and timing for `sectionId`.
- Phase 4.6 cannot finalize the Service-layer responsibility for supplying or validating `sectionId` during the stub lifecycle.

No architectural decision may be justified from the approved documentation regarding `sectionId`'s creation-time handling until this gap is resolved.

## Status

**Resolved 2026-08-06 — see ADR-004.** Re-confirmed still Open as of Phase 11
Revision 1 (`Phase-11-DG-SIS-001-Resolution-Analysis.md`) after review of all
evidence accumulated through Phase 10 — no new evidence surfaced through
ordinary design-phase work. The Requirement Owner (Preetam Sinha) then
delegated the decision rather than leave it pending; ADR-004 records the
resolution and its reasoning from the same evidence base Phase 11 already
assembled: `section_id` is nullable at stub creation, populated by Admin
Staff during FR-06 profile completion, with BR-SIS-003 enforcing it as a
non-null precondition of the `DRAFT → ACTIVE` transition specifically (not a
database-level constraint from row-creation time). See ADR-004 §1 for the
point-by-point answer to each of this record's four original questions.
