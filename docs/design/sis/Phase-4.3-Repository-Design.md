---
status: Approved (re-validated against ADR-003/DG-SIS-001 — no revision required)
last-updated: 2026-08-05
references: Phase 4.2 Revision 1, ADR-003, DG-SIS-001
---

# Phase 4.3 — SIS Model (Repository) Design

Conventions followed: one CI4 Model per entity, acting as its repository (Company Development Standard §3.3 — a dedicated Repository class is added only where data access genuinely diverges from CRUD, which is not the case here); explicit query-builder methods rather than magic finders; `existsBy…`/`existsBy…ExceptId` pairs for create/update uniqueness checks; pagination reserved for entities classified Transaction Data per Appendix-G's Data Category column (formerly cross-referenced as DDD Part-2 §11, now archived) — `Student` and `Guardian` are both classified Master, so neither Model paginates its listing methods.

## `StudentModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByAdmissionNumber(string $value): ?array` | Lookup by business key | BR-SIS-002 |
| `existsByAdmissionNumber(string $value): bool` | Create-time uniqueness check | BR-SIS-002 |
| `existsByAdmissionNumberExceptId(string $value, int $id): bool` | Update-time uniqueness check | BR-SIS-002 |
| `existsByAadhaarNumber(string $value): bool` | Create-time uniqueness check (Service calls only when Aadhaar is present) | BR-SIS-002 |
| `existsByAadhaarNumberExceptId(string $value, int $id): bool` | Update-time uniqueness check | BR-SIS-002 |
| `existsByApplicationId(int $applicationId): bool` | Prevents a second `Student` being created from the same `Application` (enforces the one-to-one) | Appendix-G ENT-SIS-001 Relationships |
| `findBySectionId(int $sectionId): array` | Section roster listing | FR-06 |
| `countBySectionIdAndStatus(int $sectionId, string $status): int` | Current occupancy of a section, scoped to ACTIVE students, for the Service layer's capacity check on transfer | BR-SIS-005 |
| `findByStatus(string $status): array` | Lifecycle-state listing | BR-SIS-003 |

## `GuardianModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByMobileNumber(string $value): array` | Lookup by candidate key; returns a list, not a single record, because Appendix-G's Unique Constraints explicitly state `mobile_number` is not enforced-unique | Appendix-G ENT-SYS-003 Index Recommendation |

## `StudentGuardianLinkModel` (composite key `student_id`, `guardian_id`)

| Method | Purpose | BR/FR basis |
|---|---|---|
| `existsByStudentIdAndGuardianId(int $studentId, int $guardianId): bool` | Create-time duplicate-link check | Appendix-H StudentGuardianLink Unique Constraints |
| `existsByStudentId(int $studentId): bool` | Determines whether any guardian link already exists for the student — input to the Service layer's ACTIVE-transition gate | BR-SIS-006 |
| `findByStudentId(int $studentId): array` | All guardians linked to a student | FR-06 |
| `findByGuardianId(int $guardianId): array` | Reverse lookup — "which students does this guardian have" | Appendix-H StudentGuardianLink Indexes |

**Deliberately excluded**: no `existsByStudentIdAndIsPrimaryContactTrue` — the "exactly one primary per student" invariant is out of enforced scope (Phase 4.2 Design Note 2); `existsByStudentId` already answers BR-SIS-006's literal requirement.

## Re-validation against ADR-003 / DG-SIS-001 / Phase 4.2 Revision 1

**No revision required.** `existsByApplicationId` is unaffected — its field/type/meaning are unchanged; only the note on which module's Service *calls* it was corrected in Phase 4.2. `findBySectionId` and `countBySectionIdAndStatus` both take a concrete, non-null `sectionId` argument and query existing rows; neither depends on whether `section_id` is guaranteed non-null at creation time. `countBySectionIdAndStatus`'s predicate (`section_id = :value AND status = ACTIVE`) is independent of how or when `section_id` came to be set on any given row, so DG-SIS-001's unresolved question does not affect this method's contract. `GuardianModel` and `StudentGuardianLinkModel` are entirely unaffected — neither `Guardian` nor `StudentGuardianLink` was touched by Phase 4.2 Revision 1.
