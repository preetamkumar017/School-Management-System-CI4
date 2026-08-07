---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, Phase 3, ADR-005
---

# Phase 4 — Examination Service Design

## `ExamService`

| Operation | Reason |
|---|---|
| `createExam(CreateExamRequest): ExamResponse` | Validates `class_id`/`academic_session_id`/`grading_scheme_id` against Academic's Services (cross-module); validates `exam_date` within session bounds; uniqueness on `(class_id, exam_name, academic_session_id)`. |
| `activateExam(int $id): ExamResponse` | `CONFIGURED → ACTIVE`, forward-only. |
| `lockExam(int $id): ExamResponse` | `ACTIVE → LOCKED`. Guards: at least one `MarksRecord` exists and none are unlocked (ADR-005 §8). On success: computes grade/GPA/rank for every student with marks in this exam and upserts their `ReportCard` row (FR-19), inside one transaction with the status transition. |
| `getExam(int $id): ExamResponse` | Plain read. |
| `listExamsByClassAndSession(int $classId, int $academicSessionId): array` | Listing. |

No delete operation — `Exam` is a historical academic record (Appendix-G:
"Permanent (historical exam record)"), same reasoning Academic's `Class`
exposes no delete.

## `MarksRecordService`

| Operation | Reason |
|---|---|
| `createMarksRecord(CreateMarksRecordRequest): MarksRecordResponse` | Validates `exam_id` exists and is not `LOCKED`/`CLOSED`; validates `student_id` (via SIS's `StudentService::getStudent`) and `subject_id` (via Academic's `SubjectService`); range-validates `marks_obtained` against `max_marks` (BR-EXM-002); runs the BR-EXM-005 eligibility stub (ADR-005 §2, always passes today); runs BR-EXM-006 anomaly flagging (ADR-005 §6) against `MarksRecordModel::findLockedByStudentAndSubjectExceptExam`; uniqueness on `(exam_id, student_id, subject_id)`. |
| `lockMarksRecord(int $id): MarksRecordResponse` | `is_locked = false → true` (BR-EXM-003). Does **not** itself trigger recalculation — recalculation is `ExamService::lockExam`'s responsibility, since a single subject locking doesn't imply the exam is complete. |
| `reevaluate(int $id, MarksRecordReevaluateRequest): MarksRecordResponse` | ADR-005 §7 — unlocks, updates `marks_obtained` (re-validated against `max_marks`), re-locks, all in one call; logs via `AuditLog::ACTION_OVERRIDE` with the required reason. If the parent `Exam` is already `LOCKED`/`CLOSED`, recomputes that one student's grade/GPA and the whole class's rank afterward (BR-EXM-004) — narrower than `ExamService::lockExam`'s full-class computation, since only one student's input changed. |
| `getMarksRecord(int $id): MarksRecordResponse` | Plain read. |
| `listMarksByExam(int $examId): array` | Listing — the roster/grading view. |

## `ReportCardService`

| Operation | Reason |
|---|---|
| `getReportCard(int $id): ReportCardResponse` | Plain read. |
| `listReportCardsByExam(int $examId): array` | Listing. |
| `publishReportCards(int $examId): array` | BR-EXM-001: guards `Exam.status = LOCKED` (i.e., every subject's marks are locked and report cards already computed by `ExamService::lockExam`); sets every `ReportCard` for the exam to `is_published = true`, `published_at = now`; transitions `Exam.status LOCKED → CLOSED`. No `Create…` operation — rows are produced only by `ExamService::lockExam` (Phase 1). |

## `PromotionService`

| Operation | Reason |
|---|---|
| `promoteStudent(CreatePromotionRecordRequest): PromotionRecordResponse` | Computes `academic_closure_confirmed` from `from_session_id`'s `AcademicSession.status = CLOSED` (Academic's `AcademicSessionService`, ADR-005 §3); computes `fee_closure_confirmed` by querying Fees' `InvoiceService::hasOutstandingBalance($studentId, $fromSessionId)` (ADR-014 §2 — no longer caller-supplied); rejects (BR-SIS-001) unless both are `true`; validates `to_class_id`'s `sequence_order` is `from_class_id`'s `+ 1` or equal (repeat); validates all four cross-module FKs exist; uniqueness on `(student_id, from_session_id)`. |
| `getPromotionRecord(int $id): PromotionRecordResponse` | Plain read. |
| `listPromotionsByToSession(int $toSessionId): array` | Listing. |

## Closed-session immutability (BR-SIS-004, ADR-005 §1)

Every mutating operation above (`activateExam`, `lockExam`,
`createMarksRecord`, `lockMarksRecord`, `reevaluate`,
`publishReportCards`) first resolves the record's governing
`academic_session_id` (directly on `Exam`, or via `Exam.academic_session_id`
for `MarksRecord`/`ReportCard`) and, if that session's status is `CLOSED`
or `ARCHIVED`, requires a non-empty `override_reason` (Phase 3) — absent,
the call throws `RECORD_LOCKED_BY_CLOSED_SESSION`; present, the call
proceeds and is separately logged via `AuditLog::ACTION_OVERRIDE`
alongside the operation's own audit entry. `PromotionService.promoteStudent`
applies the same guard against `from_session_id`.

## Cross-module exposure

Every Service above is called by other modules only through its own
public methods, per the cross-module rule. No module reaches into
`ExamModel`/`MarksRecordModel`/`ReportCardModel`/`PromotionRecordModel`
directly. Examination itself calls Academic's `ClassService`,
`AcademicSessionService`, `GradingSchemeService`, `SubjectService`, and
SIS's `StudentService` — never their Models.
