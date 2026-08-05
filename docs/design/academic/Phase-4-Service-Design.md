---
status: Approved (Revision 1) — fully finalized
last-updated: 2026-08-06
references: Phase 1, Phase 2, Phase 3, ADR-004
---

# Phase 4 — Academic Service Design

## `AcademicSessionService`

| Operation | Status | Reason |
|---|---|---|
| `createSession(CreateAcademicSessionRequest $request): AcademicSessionResponse` | Approved | Validates `session_name` uniqueness and date non-overlap (`AcademicSessionModel::findOverlapping`) before persisting. |
| `updateSession(int $id, UpdateAcademicSessionRequest $request): AcademicSessionResponse` | Approved | Re-validates uniqueness/non-overlap excluding the record being updated. |
| `changeStatus(int $id, AcademicSessionStatusChangeRequest $request): AcademicSessionResponse` | Approved | Forward-only transition (BR-SIS-001); transitioning to `CLOSED` triggers the archival-eligibility policy (Appendix-F NFR-ARC-001) as a downstream effect, not a synchronous side effect of this method — the actual archival job is an operational/scheduled concern, not part of this Service call. |
| `getSession(int $id): AcademicSessionResponse` | Approved | Plain read. |
| `listSessions(): array` | Approved | Master-data listing, no pagination. |
| `getCurrentActiveSession(): ?AcademicSessionResponse` | Approved | Convenience read used by other modules (via this Service, never via `AcademicSessionModel` directly) — e.g. Admission defaulting a new `Application` to the current session. |

## `ClassService`

| Operation | Status |
|---|---|
| `createClass`, `updateClass`, `getClass`, `listClasses` | Approved — plain CRUD, uniqueness on `class_name`/`sequence_order` enforced per Phase 2. |

No delete operation is exposed — Phase 1 states `Class` is "never hard-deleted... only deactivated," so the only removal path is the standard soft-delete, itself gated by whatever RBAC/approval rule the Company Development Standard's security rules require for a destructive-looking action on foundational master data referenced by nearly every module.

## `SectionService`

| Operation | Status | Reason |
|---|---|---|
| `createSection(CreateSectionRequest $request): SectionResponse` | Approved | Validates `class_id` exists and `(class_id, section_name)` uniqueness. |
| `updateSection(int $id, UpdateSectionRequest $request): SectionResponse` | Approved | `class_id` immutable per Phase 3 — request DTO doesn't carry it for update. |
| `getSection(int $id): SectionResponse` | Approved | Plain read — this is the method Admission/SIS call (via this Service, never `SectionModel` directly) to validate a `section_id` and read its `capacity` during their own orchestration. |
| `listSectionsByClass(int $classId): array` | Approved | Plain read. |

Academic does **not** expose an occupancy-count operation — occupancy of active students in a section is SIS's own responsibility (see `docs/design/sis/Phase-4.6`'s `changeStatus`/`transferSection`), computed against SIS's own `students` table. DG-SIS-001 (whether/when a `section_id` is assigned during Admission's stub-creation flow) is resolved by ADR-004: not at creation, assigned later during FR-06 completion — Academic's role is unaffected either way, exactly as this document anticipated.

## `SubjectService`

| Operation | Status |
|---|---|
| `createSubject`, `updateSubject`, `getSubject`, `listSubjects` | Approved — plain CRUD, uniqueness on `subject_code` per Phase 2. |

## `GradingSchemeService`

| Operation | Status | Reason |
|---|---|---|
| `createGradingScheme(CreateGradingSchemeRequest $request): GradingSchemeResponse` | Approved | Validates `scheme_name` uniqueness and `grade_band_json` structural validity (non-overlapping ascending bands). |
| `updateGradingScheme(int $id, UpdateGradingSchemeRequest $request): GradingSchemeResponse` | **Decided** | **No versioning mechanism is built.** Calls `GradingSchemeModel::isReferencedByClosedExam($id)` first: if false, mutates `grade_band_json`/`board_type` in place; if true, throws a Business Rule exception (`GRADING_SCHEME_LOCKED_BY_CLOSED_EXAM`) — the caller creates a **new** `GradingScheme` row (a new `scheme_name`, e.g. "CBSE Standard Grading 2027") via `createGradingScheme` instead. This reuses ordinary CRUD rather than adding a version-history table: once a scheme is referenced by a closed exam it becomes effectively immutable (protected the same way any RESTRICT-only FK protects a referenced row), and a "new version" is simply a new named scheme. |
| `getGradingScheme(int $id): GradingSchemeResponse` | Approved | Plain read. |
| `listGradingSchemes(): array` | Approved | Plain read. |

## `ClassSubjectMapService`

| Operation | Status |
|---|---|
| `mapSubjectToClass(ClassSubjectMapRequest $request): ClassSubjectMapResponse` | Approved — rejects duplicate `(class_id, subject_id)` pairs. |
| `unmapSubjectFromClass(int $classId, int $subjectId): void` | Approved. |
| `listSubjectsForClass(int $classId): array` | Approved. |

## Cross-module exposure

Every other module that depends on Academic Master Data (Admission, SIS, Examination, Fees, HR & Payroll indirectly via Attendance — see `docs/design/School-ERP-Module-Architecture.md`) calls these Service classes' public methods directly. No module reaches into `AcademicSessionModel`, `ClassModel`, `SectionModel`, `SubjectModel`, `GradingSchemeModel`, or `ClassSubjectMapModel` — per the Company Development Standard's cross-module rule and ADR-001.

## Conclusion

**Fully approved, no Open items.** `GradingSchemeService::updateGradingScheme`'s versioning question is decided (no versioning mechanism; closed-exam references make a scheme immutable, new schemes are created instead); every operation across all six services is ready for implementation.
