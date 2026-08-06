---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 — Domain Model
---

# Phase 2 — Examination Model (Repository) Design

Convention: one CI4 Model per entity (Company Development Standard §3.3);
all four entities are Transaction-classified — `ExamModel`/`MarksRecordModel`/
`ReportCardModel` don't need pagination (queried by exam/class scope, not
listed wholesale), but `PromotionRecordModel`'s year-end listing does, same
reasoning Admission's `ApplicationModel` used.

## `ExamModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `existsByClassExamNameSession(int $classId, string $examName, int $academicSessionId): bool` / `...ExceptId(...)` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `findByClassAndSession(int $classId, int $academicSessionId): array` | Listing for a class/session | FR-17 |

## `MarksRecordModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByExamStudentSubject(int $examId, int $studentId, int $subjectId): ?MarksRecord` | Lookup by business key | Uniqueness (Phase 1) |
| `existsByExamStudentSubject(int $examId, int $studentId, int $subjectId): bool` | Create-time uniqueness check | Uniqueness (Phase 1) |
| `findByExamId(int $examId): array` | All marks for an exam — input to completeness/lock checks (ADR-005 §8) and grade/GPA/rank calculation | FR-19 |
| `findLockedByStudentAndSubjectExceptExam(int $studentId, int $subjectId, int $exceptExamId): array` | Historical locked marks for the same student/subject, across other exams — input to BR-EXM-006 anomaly flagging | BR-EXM-006 |
| `countUnlockedByExamId(int $examId): int` | Completeness check input — "every entered mark is locked" (ADR-005 §8) | FR-19 |

## `ReportCardModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByStudentAndExam(int $studentId, int $examId): ?ReportCard` | Lookup by business key | Uniqueness (Phase 1) |
| `findByExamId(int $examId): array` | All report cards for an exam — input to publish (BR-EXM-001) and rank computation | FR-20, FR-21 |

## `PromotionRecordModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `existsByStudentAndFromSession(int $studentId, int $fromSessionId): bool` | Create-time uniqueness check | Uniqueness (Phase 1) |
| `findByToSession(int $toSessionId): array` | Paginated? No — Master-scale listing per session, same reasoning as Academic's non-paginated Models; a school's single-session promotion batch is bounded (one row per student, not an open-ended transaction log) | FR-08 |
