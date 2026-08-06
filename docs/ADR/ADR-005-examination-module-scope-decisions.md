---
status: Accepted
date: 2026-08-06
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-06 — see Context, same delegation ADR-004 records)
relates-to: ADR-002 (deferred BR-SIS-004 to this module); Appendix-C v1.1 (BR-EXM-001 through BR-EXM-007, BR-SIS-001, BR-SIS-004); Appendix-E (FR-17 through FR-21)
---

# ADR-005: Examination module scope — BR-SIS-004 resolution, undesigned-module dependencies, and unspecified calculation formulas

## Context

Examination (`ENT-EXM-001` through `ENT-EXM-004`: `Exam`, `MarksRecord`,
`ReportCard`, `PromotionRecord`) is next per the development roadmap's
suggested Stage 6 order — it closes ADR-002's deferred BR-SIS-004 and
depends only on Academic (already built). Working through Appendix-C's
seven `BR-EXM-*` rules, BR-SIS-001, and Appendix-E's FR-17 through FR-21
surfaces several items with no prior resolution:

1. **BR-SIS-004** ("Historical Record Immutability") was deferred here by
   ADR-002 with no governing entity identified — Appendix-G files it under
   SIS but no SIS entity is a historical/transactional record.
2. **BR-EXM-005** ("Exam Eligibility Precondition") requires Attendance's
   `BR-ATT-006` "Exam Eligibility At Risk" flag — the Attendance module is
   not designed or built (`docs/design/School-ERP-Module-Architecture.md`).
3. **`PromotionRecord.fee_closure_confirmed`** (BR-SIS-001) requires a fee
   ledger closure signal — the Fees module is not designed or built.
4. **BR-EXM-007** (board-aligned grading scheme) requires a confirmed
   "board affiliation" setting — no such configuration entity exists
   anywhere in the approved system; Administration's `Configuration`
   entity itself is explicitly undesigned/deferred
   (`School-ERP-Module-Architecture.md`'s Administration row).
5. **GPA formula** is never specified — Appendix-E FR-19 §27 states
   "cross-section ranking policy... Client/Product Decision Required" and
   §35 states "tie-breaking rule is Client/Product Decision Required."
   Appendix-C's BR-EXM-006 §7 states the anomaly deviation threshold is
   itself "Client/Product Decision Required."
6. **FR-20**'s literal output is "Generated report card PDF per student."
   No PDF templating library or rendering approach has been selected
   anywhere in this project, and Administration's `Document` entity (which
   FR-20's own Appendix-G relationship line names as the child entity for
   the generated file) is undesigned/deferred, same status as
   `Configuration`.
7. **BR-EXM-003**'s re-evaluation workflow requires "Academic Head
   approval" — Administration's `ApprovalRequest` entity is undesigned/
   deferred, same status as `Configuration`/`Document`.
8. **FR-19**'s trigger is "the final subject for a class/exam is locked,"
   detected by comparing against "all subjects for the class" — the
   authoritative subject list lives in Academic (`ClassSubjectMap`) and
   the authoritative student roster lives in SIS (`Student.section_id`),
   crossed against Academic's `Section`. No document states whether
   completeness means "every mapped subject × every active student in
   the class's sections" or something narrower.

Per the same delegation ADR-004 recorded (Preetam Sinha, 2026-08-06,
"outstanding decisions be made rather than left pending"), each item below
is resolved from what the approved documents already establish, and where
a choice is genuinely open, it is stated as a decision, not disguised as a
derivation — matching ADR-004's own standard.

## Decision

### 1. BR-SIS-004 is resolved as academic-session-closure immutability, enforced in Examination

Every `Exam`, `MarksRecord`, `ReportCard`, and `PromotionRecord` row
belongs (directly or via its `Exam`) to an `AcademicSession`. BR-SIS-004's
own text — "academic records for a **closed academic year** cannot be
edited" — already names the governing condition; Academic's
`AcademicSession.status` (`PLANNED`/`ACTIVE`/`CLOSED`/`ARCHIVED`,
`docs/design/academic/Phase-1`) already models it. No new field or entity
is needed: a Service-layer guard in Examination rejects any mutation on a
record whose `Exam.academic_session_id` resolves (via Academic's
`AcademicSessionService`) to a `CLOSED` or `ARCHIVED` session, **unless**
the caller supplies a reason, in which case the mutation proceeds and is
logged via `AuditLog::ACTION_OVERRIDE` — the exact mechanism
`AuditService::record()` already enforces (a mandatory reason for that
action). This reuses Administration's existing override contract rather
than inventing a parallel one, and satisfies "corrections require a
logged administrative override" without a dedicated approval workflow
(item 7 explains why no such workflow exists to invoke instead).

This resolves BR-SIS-004 as belonging to Examination's own entities, not
SIS's `Student` — consistent with ADR-002's finding that no SIS entity
could ever have satisfied it.

### 2. BR-EXM-005 (exam eligibility) is stubbed pending Attendance, same pattern as Academic's Stage 3 precedent

`MarksRecordService`'s eligibility check always returns "eligible" — no
`BR-ATT-006` flag exists anywhere to check. This is the identical shape
to `GradingSchemeModel::isReferencedByClosedExam` returning `false`
throughout Stage 3/4 (documented then as "always false until Examination
exists"); this ADR now provides the other half of that seam (§9 below)
and creates the same kind of seam for Attendance to fill in later.

**Closed by ADR-006 §11** (2026-08-06, same day): once Attendance existed,
`MarksRecordService::createMarksRecord` was updated to call
`AttendanceService::isExamEligibilityAtRisk` for real, requiring a logged
override when a student is flagged at-risk. This paragraph is left as
written for the historical record of what was stubbed and why.

### 3. `PromotionRecord.fee_closure_confirmed` is caller-supplied, not system-computed

No Fees module exists to query a ledger balance from.
`academic_closure_confirmed` **is** system-computed (from
`AcademicSession.status = CLOSED`, the same condition §1 uses).
`fee_closure_confirmed` is accepted as an explicit boolean on the
promotion request — Academic Head attests to it — until Fees exists to
supply it automatically. `PromotionService.promoteStudent` still enforces
BR-SIS-001's gate (both flags must be `true`), it just can't independently
verify the fee half yet.

### 4. BR-EXM-007 (board-alignment cross-check) is not enforced

No "confirmed board affiliation" setting exists anywhere in the system —
Administration's `Configuration` entity, the only place such a setting
could live, is itself undesigned. `GradingScheme.board_type` (Academic,
Stage 3) is trusted as correct at scheme-creation time; Examination does
not re-derive or cross-check it against anything, since there is nothing
approved to check it against. A future `Configuration` module closes this
gap; it is not invented here.

### 5. GPA formula and class rank — decided, not deferred

**Per-subject grade point** = `min(9.99, round(marks_obtained / max_marks
* 10, 2))`. **`ReportCard.gpa`** = the average of a student's subject
grade points for that exam, rounded to 2 decimal places. The `min(9.99,
...)` clamp exists because Appendix-G's own attribute catalogue types
`gpa` as `DECIMAL(3,2)` (max `9.99`) — a perfect subject score would
otherwise compute to exactly `10.00`, which the approved column width
cannot hold; matches Appendix-G's own example value (`9.20`), consistent
with a base-10 scale.

**`ReportCard.class_rank`** = standard competition ranking ("1224") by
GPA descending within the `(class_id, exam_id)` scope: tied students
share the same rank, and the next distinct rank is offset by the number
of students tied above it. This is the most common convention for
"class rank" in the Indian schooling context these documents target, and
resolves FR-19 §35's open tie-breaking question.

Grade **per subject** (not GPA) is looked up from `Exam.grading_scheme_id`
→ `GradingScheme.grade_band_json` (Academic, Stage 3) using the subject's
percentage (`marks_obtained / max_marks * 100`), reusing the same
non-overlapping-band structure Academic's `GradingSchemeService` already
validates at scheme-creation time — no new grade-band logic is invented,
only consumed.

### 6. BR-EXM-006 anomaly threshold — decided

A newly entered mark is flagged (`is_flagged = true`) when the student has
at least one other **locked** `MarksRecord` for the same `subject_id`
(any exam) and the new mark's percentage deviates from the average
percentage of those historical records by more than **30 percentage
points**. With zero historical records, nothing is flagged — there is no
average to deviate from. 30 points is a deliberately conservative,
documented default (roughly "a full letter-grade-band swing or more" on a
typical 10-band CBSE-style scheme); it is a configuration value, not a
hardcoded law, and a future `Configuration` entity is the natural place
to make it school-tunable — not invented here for the same reason as
item 4.

### 7. Re-evaluation is a single logged action, not an `ApprovalRequest` workflow

BR-EXM-003's "logged re-evaluation workflow with Academic Head approval"
has no `ApprovalRequest` entity to route through — undesigned, same as
`Configuration`/`Document`. `MarksRecordService::reevaluate(int $id,
float $newMarksObtained, string $reason)` unlocks, updates, and re-locks
the record in one call, requiring a mandatory `$reason`, logged via
`AuditLog::ACTION_OVERRIDE` (same mechanism as §1). This satisfies "logged
... with before/after values" (Appendix-C §14) without a multi-step
approval state machine this codebase has no entity for yet. Per BR-EXM-004,
a successful re-evaluation re-triggers grade/GPA/rank recalculation for
that student (not the whole class — only the affected student's row
changes; class rank is recomputed for the class since one student's GPA
shifting can change others' relative rank).

### 8. FR-19 completeness is scoped to "every entered mark is locked," not full roster/subject cross-reference

`Exam` completeness ("all subjects locked") is evaluated as: at least one
`MarksRecord` exists for the exam, and every `MarksRecord` row that exists
for it has `is_locked = true`. This does **not** cross-check against
Academic's `ClassSubjectMap` (which subjects the class is supposed to
have) or SIS's active roster (which students should have a mark) — doing
so would require Examination to reach into two other modules' full
listings and reconcile them against a policy no document specifies (e.g.,
what about a student who transferred out mid-year, or a subject added
after marks entry started). This is a bounded, explicit scope reduction:
`ExamService::lockExam(int $id)` is an explicit Academic-Head action
(`POST .../lock`), not the fully event-driven "final subject auto-locks
the exam" FR-19 describes — matching this project's established pattern
of scoping down auto-detection in favor of an explicit, safe trigger
(e.g., Academic's `GradingScheme` update-vs-new-scheme decision, Phase 4).

### 9. Report card generation produces a data record, not a PDF file

FR-20's "Generated report card PDF per student" is scoped to
`ReportCard`'s row (`grade_summary`, `gpa`, `class_rank`) — the
calculated-data compilation FR-20 describes, computed from locked
`MarksRecord`s. No PDF file is rendered or stored; Administration's
`Document` entity (FR-20's own named child entity for "the generated PDF")
is undesigned, and no PDF templating library has been selected anywhere
in this project. This is the same shape as Academic's `GradingScheme`
versioning deferral and Admission's BR-ADM-005 missing-documents gap: a
named, bounded exclusion, not a silent gap. A future iteration wires
actual PDF rendering once `Document`/`Configuration` exist; the data this
module produces (`grade_summary`/`gpa`/`class_rank`) is exactly what that
future work would need as input.

### 10. `GradingSchemeModel::isReferencedByClosedExam` is resolved via a locally-stored flag, not a reverse query into Examination

Academic's Stage 3 implementation stubbed this to always return `false`,
documented then as "there is no `exams` table yet... always false until
Examination exists." The naive fix — have `GradingSchemeModel` query
Examination's `exams` table directly — is **rejected**: it would make
Academic depend on Examination while Examination already depends on
Academic (`ClassService`, `AcademicSessionService`, `SubjectService`,
`GradingSchemeService`), a dependency cycle the Company Development
Standard's no-cycle rule (§1.1/§11, already invoked by ADR-003 for
Admission↔SIS) forbids. This is the same shape of bidirectional need
ADR-003 resolved for Admission↔SIS, resolved the same way: pick one
call direction, and give the other side what it needs without a
reverse dependency.

**Resolution:** Academic's `grading_schemes` table gains one additive
column, `locked_by_closed_exam BOOLEAN DEFAULT FALSE`, and a new public
method `GradingSchemeService::lockSchemeReferencedByClosedExam(int
$schemeId): void` that sets it (idempotent). `GradingSchemeModel::
isReferencedByClosedExam` now simply reads that column on the scheme's
own row — no query into `exams`, no new inbound dependency. Examination
is the one that calls outward (the already-established, allowed
direction): `ReportCardService::publishReportCards` — the operation that
transitions `Exam.status → CLOSED` (Phase 4) — calls
`GradingSchemeService::lockSchemeReferencedByClosedExam($exam->
grading_scheme_id)` as part of that same operation. The information
flows the same direction the dependency already does; Academic never
needs to ask Examination anything.

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s Examination row is
  updated from "Not yet designed" to designed, citing this ADR alongside
  ADR-002.
- `docs/design/examination/Phase-1` through `Phase-5` (this module's own
  design set) are written on the basis of every decision above; none of
  them re-derive or revisit these items.
- Items 2–4 and 6 name real, bounded gaps this ADR does not invent
  workarounds to hide — each is either a documented stub (item 2, mirrors
  existing precedent) or a documented, tunable default (items 6) or an
  explicitly out-of-scope cross-check with a stated reason (items 3, 4).
- Attendance's future design must account for item 2's seam
  (`MarksRecordService`'s eligibility check) the same way Examination
  itself was the seam Academic's Stage 3 GradingScheme work anticipated.
- Fees' future design must account for item 3's seam
  (`PromotionRecord.fee_closure_confirmed` moving from caller-supplied to
  system-computed).
- A future `Configuration` module design must account for items 4 and 6
  (board affiliation, anomaly threshold) as candidate settings.
