---
status: Approved (Original)
last-updated: 2026-08-06
references: Appendix-G Data Dictionary v1.0 (EXM module entities), Appendix-C v1.1 (BR-EXM-001–007, BR-SIS-001, BR-SIS-004), ADR-002, ADR-005, Company Development Standard
---

# Phase 1 — Examination Domain Model

## Scope

Per ADR-002 (module ownership) and ADR-005 (scope decisions): `Examination`
(`App\Modules\Examination`) owns four entities, all classified Transaction
data: `Exam`, `MarksRecord`, `ReportCard`, `PromotionRecord`. Field lists
below are taken directly from Appendix-G's EXM module entity cards — this
document adds no field Appendix-G doesn't already specify. Every scope
decision referenced below (eligibility stub, fee-closure caller-supplied
flag, GPA/rank formula, anomaly threshold, report-card-as-data-not-PDF,
re-evaluation-without-ApprovalRequest, lock-completeness scoping) is
ADR-005's, not re-derived here.

---

## Entity: `Exam` (ENT-EXM-001, table `exams`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| exam_name | VARCHAR(50) | N | – | Non-empty |
| class_id | BIGINT UNSIGNED | N | – | FK → Academic's `classes` (cross-module, plain FK, validated via `ClassService`) |
| academic_session_id | BIGINT UNSIGNED | N | – | FK → Academic's `academic_sessions` (cross-module, plain FK, validated via `AcademicSessionService`) |
| grading_scheme_id | BIGINT UNSIGNED | N | – | FK → Academic's `grading_schemes` (cross-module, plain FK, validated via `GradingSchemeService`); BR-EXM-007's board-alignment cross-check is not enforced (ADR-005 §4) |
| exam_date | DATE | N | – | Must fall within `academic_session_id`'s date bounds |
| status | enum (`CONFIGURED`, `ACTIVE`, `LOCKED`, `CLOSED`) | N | CONFIGURED | Forward-only; `LOCKED` reached via explicit `ExamService::lockExam` (ADR-005 §8), `CLOSED` reached via `ReportCardService::publishReportCards` (BR-EXM-001) |

Unique constraint: `(class_id, exam_name, academic_session_id)`. No
database-level FK to any Academic table (cross-module rule). Relationships:
one-to-many with `MarksRecord`, `ReportCard` (both intra-module, real FKs).

### `Exam` Lifecycle

`CONFIGURED` (marks entry not yet open — in this codebase, `MarksRecord`
creation is allowed from `CONFIGURED` onward since no separate
"activation" input exists beyond the status field itself; `ACTIVE` is the
caller-driven signal that entry has formally begun) → `ACTIVE` → `LOCKED`
(every existing `MarksRecord` is locked, ADR-005 §8) → `CLOSED` (report
cards published, BR-EXM-001). Forward-only; mutation of a `CLOSED` or
`ARCHIVED`-session-scoped `Exam` requires the BR-SIS-004 override (ADR-005
§1).

---

## Entity: `MarksRecord` (ENT-EXM-002, table `marks_records`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| exam_id | BIGINT UNSIGNED | N | – | FK → `exams`, RESTRICT (intra-module, real FK) |
| student_id | BIGINT UNSIGNED | N | – | FK → SIS's `students` (cross-module, plain FK — SIS exposes no read-only "exists" check today, so this is validated by presence in `StudentService::getStudent`, same pattern as every other cross-module existence check in this codebase) |
| subject_id | BIGINT UNSIGNED | N | – | FK → Academic's `subjects` (cross-module, plain FK, validated via `SubjectService`) |
| marks_obtained | DECIMAL(5,2) | Y | NULL | `0 ≤ value ≤ max_marks`; `NULL` = absent (BR-EXM-002) |
| max_marks | DECIMAL(5,2) | N | – | Positive |
| is_flagged | BOOLEAN | N | FALSE | Set automatically at entry time (BR-EXM-006, ADR-005 §6) |
| is_locked | BOOLEAN | N | FALSE | BR-EXM-003 |

Unique constraint: `(exam_id, student_id, subject_id)`.

### `MarksRecord` Lifecycle

Created (entered) → Flagged (if anomalous, BR-EXM-006) → Locked (BR-EXM-003)
→ Re-evaluated (single logged action, ADR-005 §7, which unlocks, updates,
and re-locks in one call — not a persisted intermediate "unlocked for
re-evaluation" status, since nothing distinguishes that state from
`CONFIGURED`/pre-lock other than the audit trail already captures).

---

## Entity: `ReportCard` (ENT-EXM-003, table `report_cards`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| student_id | BIGINT UNSIGNED | N | – | FK → SIS's `students` (cross-module, plain FK) |
| exam_id | BIGINT UNSIGNED | N | – | FK → `exams`, RESTRICT (intra-module, real FK) |
| grade_summary | JSON | N | – | Per-subject grade, e.g. `{"MATH":"A1"}` — derived from locked `MarksRecord`s via `Exam.grading_scheme_id` |
| gpa | DECIMAL(3,2) | N | – | ADR-005 §5's formula; clamped to the column's own `9.99` ceiling |
| class_rank | INT | Y | NULL | ADR-005 §5's competition-ranking formula |
| is_published | BOOLEAN | N | FALSE | BR-EXM-001 |
| published_at | DATETIME | Y | NULL | Set when `is_published` becomes true |

Unique constraint: `(student_id, exam_id)`. No `Document` child entity is
created (ADR-005 §9 — data record only, no PDF).

### `ReportCard` Lifecycle

Created (generated from locked marks, via `ExamService::lockExam`) →
Published (`ReportCardService::publishReportCards`, BR-EXM-001, which also
transitions the parent `Exam` to `CLOSED`).

---

## Entity: `PromotionRecord` (ENT-EXM-004, table `promotion_records`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| student_id | BIGINT UNSIGNED | N | – | FK → SIS's `students` (cross-module, plain FK) |
| from_session_id | BIGINT UNSIGNED | N | – | FK → Academic's `academic_sessions` (cross-module, plain FK) |
| to_session_id | BIGINT UNSIGNED | N | – | FK → Academic's `academic_sessions` (cross-module, plain FK) |
| from_class_id | BIGINT UNSIGNED | N | – | FK → Academic's `classes` (cross-module, plain FK) |
| to_class_id | BIGINT UNSIGNED | N | – | FK → Academic's `classes` (cross-module, plain FK); must have `sequence_order = from_class's sequence_order + 1`, or equal (repeat) |
| academic_closure_confirmed | BOOLEAN | N | FALSE | System-computed from `from_session_id`'s `AcademicSession.status = CLOSED` (ADR-005 §3); must be `TRUE` to promote (BR-SIS-001) |
| fee_closure_confirmed | BOOLEAN | N | FALSE | Caller-supplied (ADR-005 §3 — Fees module doesn't exist yet); must be `TRUE` to promote (BR-SIS-001) |

Unique constraint: `(student_id, from_session_id)`.

### `PromotionRecord` Lifecycle

Created at year-end rollover (both closure flags checked at creation time,
BR-SIS-001) → Confirmed (creation itself is confirmation — no separate
draft state is specified anywhere) → subject to the same BR-SIS-004
closed-session immutability as every other Examination entity once its
`from_session_id` session is `CLOSED`/`ARCHIVED`.

## Out of scope

- Any Attendance-side implementation of BR-ATT-006 (ADR-005 §2).
- Any Fees-side implementation of fee-ledger closure (ADR-005 §3).
- A `Configuration` entity for board-affiliation or anomaly-threshold
  settings (ADR-005 §4, §6).
- PDF rendering / `Document` persistence for report cards (ADR-005 §9).
- A full `ApprovalRequest`-routed re-evaluation workflow (ADR-005 §7).
