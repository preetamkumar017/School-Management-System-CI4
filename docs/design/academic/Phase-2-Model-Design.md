---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 — Domain Model
---

# Phase 2 — Academic Model (Repository) Design

Convention: one CI4 Model per entity, acting as its repository (Company Development Standard §3.3); all five entities are classified Master data, so none of these Models paginate their listing methods (pagination is reserved for Transaction-classified data per the Company Development Standard's Database Standards).

## `AcademicSessionModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findBySessionName(string $value): ?array` | Lookup by business key | Uniqueness (Phase 1) |
| `existsBySessionName(string $value): bool` | Create-time uniqueness check | Uniqueness (Phase 1) |
| `existsBySessionNameExceptId(string $value, int $id): bool` | Update-time uniqueness check | Uniqueness (Phase 1) |
| `findOverlapping(string $startDate, string $endDate, ?int $exceptId = null): array` | Returns any existing session whose date range overlaps the given range — input to the Service layer's non-overlap check (a cross-row rule, not a DB constraint) | Phase 1 non-overlap rule |
| `findByStatus(string $status): array` | Lifecycle-state listing (e.g. find the current `ACTIVE` session) | BR-SIS-001 |

## `ClassModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByClassName(string $value): ?array` | Lookup by business key | Uniqueness (Phase 1) |
| `existsByClassName(string $value): bool` / `existsByClassNameExceptId(string $value, int $id): bool` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `existsBySequenceOrder(int $value): bool` / `existsBySequenceOrderExceptId(int $value, int $id): bool` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `findAllOrderedBySequence(): array` | Full class list for promotion-order logic and dropdown population | Phase 1 |

## `SectionModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByClassId(int $classId): array` | All sections under a class | FR-06, FR-14 |
| `existsByClassIdAndSectionName(int $classId, string $sectionName): bool` / `...ExceptId(...)` | Create/update-time uniqueness within a class | Uniqueness (Phase 1) |
| `find(int $id): ?array` | Plain read — used by SIS/other modules' Service layer to validate a `section_id` and read its `capacity` | DG-SIS-001 (Academic's side of the still-open SIS question — see Phase 1) |
| `countActiveOccupancy(int $sectionId, string $activeStatusValue): int` | **Not owned by Academic** — occupancy of a section by active students is SIS's own count against its own `students` table (see `docs/design/sis/Phase-4.3`'s `countBySectionIdAndStatus`), not something Academic computes. Listed here only to make explicit that Academic's `SectionModel` does *not* duplicate it. | — |

## `SubjectModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findBySubjectCode(string $value): ?array` | Lookup by business key | Uniqueness (Phase 1) |
| `existsBySubjectCode(string $value): bool` / `...ExceptId(...)` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `findByClassId(int $classId): array` | Subjects mapped to a class, via `ClassSubjectMap` — a join, not a direct FK | Subject's Many-to-Many relationship (Phase 1) |

## `GradingSchemeModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findBySchemeName(string $value): ?array` | Lookup by business key | Uniqueness (Phase 1) |
| `existsBySchemeName(string $value): bool` / `...ExceptId(...)` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `isReferencedByClosedExam(int $schemeId): bool` | Input to the Service layer's immutability check (Phase 4's decision) — whether a scheme may still be mutated in place, or the caller must create a new scheme instead | Phase 4 |

## `ClassSubjectMapModel` (composite key `class_id`, `subject_id`)

| Method | Purpose | BR/FR basis |
|---|---|---|
| `existsByClassIdAndSubjectId(int $classId, int $subjectId): bool` | Create-time duplicate-mapping check | Phase 1 |
| `findByClassId(int $classId): array` | Subjects mapped to a class | Phase 1 |
| `findBySubjectId(int $subjectId): array` | Reverse lookup — classes a subject is taught in | Phase 1 |
