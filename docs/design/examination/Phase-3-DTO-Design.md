---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, ADR-005
---

# Phase 3 — Examination DTO Design

Convention: one Response DTO per entity, wrapping the Entity and exposing
`toArray()` — Academic's convention (Stage 3), not SIS's Mapper-class
convention; Examination's own design doesn't call for dedicated Mapper
classes (no document requires them, same reasoning Academic gave for
skipping them). A `Create…Request` plus dedicated transition DTOs for
status/lock/publish actions, mirroring Academic's `AcademicSession` and
Admission's `Application` pattern.

## `CreateExamRequest`

| Field | Validation rule |
|---|---|
| exam_name | `required`, max length 50 |
| class_id | `required` (validated against Academic's `ClassService`) |
| academic_session_id | `required` (validated against Academic's `AcademicSessionService`) |
| grading_scheme_id | `required` (validated against Academic's `GradingSchemeService`) |
| exam_date | `required`, valid date, within `academic_session_id`'s bounds |

`status` excluded — set to `CONFIGURED` at creation, changed only via
`POST .../activate` / `POST .../lock` / (indirectly) `POST
.../publish-report-cards`.

## `ExamResponse`

Fields: `exam_id`, `exam_name`, `class_id`, `academic_session_id`,
`grading_scheme_id`, `exam_date`, `status`.

## `CreateMarksRecordRequest`

| Field | Validation rule |
|---|---|
| exam_id | `required` |
| student_id | `required` |
| subject_id | `required` |
| marks_obtained | optional (`NULL` = absent), `0 ≤ value ≤ max_marks` (BR-EXM-002) |
| max_marks | `required`, positive |

## `MarksRecordReevaluateRequest`

| Field | Validation rule |
|---|---|
| marks_obtained | `required`, `0 ≤ value ≤ max_marks` |
| reason | `required`, non-empty (ADR-005 §7 — logged via `AuditLog::ACTION_OVERRIDE`) |

## `MarksRecordResponse`

Fields: `marks_record_id`, `exam_id`, `student_id`, `subject_id`,
`marks_obtained`, `max_marks`, `is_flagged`, `is_locked`.

## `ReportCardResponse`

Fields: `report_card_id`, `student_id`, `exam_id`, `grade_summary`, `gpa`,
`class_rank`, `is_published`, `published_at`.

No `Create…Request` — `ReportCard` rows are produced only as a side effect
of `ExamService::lockExam` (Phase 4), never created directly by a client.

## `CreatePromotionRecordRequest`

| Field | Validation rule |
|---|---|
| student_id | `required` |
| from_session_id | `required` |
| to_session_id | `required` |
| from_class_id | `required` |
| to_class_id | `required`; `sequence_order` must equal `from_class_id`'s `+ 1`, or be equal (repeat) — Service-layer check |
| fee_closure_confirmed | `required`, boolean (ADR-005 §3 — caller-supplied) |

`academic_closure_confirmed` excluded — system-computed (ADR-005 §3).

## `PromotionRecordResponse`

Fields: `promotion_record_id`, `student_id`, `from_session_id`,
`to_session_id`, `from_class_id`, `to_class_id`,
`academic_closure_confirmed`, `fee_closure_confirmed`.

## Closed-session override — shared shape

Every mutating endpoint on a record whose `Exam`/`from_session_id` session
is `CLOSED`/`ARCHIVED` accepts an optional `override_reason` field
(ADR-005 §1). When the session is not closed, the field is ignored if
present. When it is closed, the field's absence rejects the request with
`RECORD_LOCKED_BY_CLOSED_SESSION`; its presence proceeds and is logged via
`AuditLog::ACTION_OVERRIDE`.
