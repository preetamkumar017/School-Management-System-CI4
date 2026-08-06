---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 through Phase 4, ADR-005
---

# Phase 5 — Examination Controller Design

Convention: one CI4 Controller per aggregate, extending
`App\Core\BaseController`, base path `/api/v1/examination/...`; every
response wrapped in the standard response envelope.

## `ExamController` — base path `/api/v1/examination/exams`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create exam | `POST /` | `createExam(...)` |
| Activate | `POST /{id}/activate` | `activateExam(int)` |
| Lock | `POST /{id}/lock` | `lockExam(int)` |
| Get exam | `GET /{id}` | `getExam(int)` |
| List by class/session | `GET /?class_id={classId}&academic_session_id={sessionId}` | `listExamsByClassAndSession(int, int)` |

## `MarksRecordController` — base path `/api/v1/examination/marks-records`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create marks record | `POST /` | `createMarksRecord(...)` |
| Lock | `POST /{id}/lock` | `lockMarksRecord(int)` |
| Re-evaluate | `POST /{id}/reevaluate` | `reevaluate(int, ...)` |
| Get marks record | `GET /{id}` | `getMarksRecord(int)` |
| List by exam | `GET /?exam_id={examId}` | `listMarksByExam(int)` |

## `ReportCardController` — base path `/api/v1/examination/report-cards`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Publish (batch, by exam) | `POST /publish?exam_id={examId}` | `publishReportCards(int)` |
| Get report card | `GET /{id}` | `getReportCard(int)` |
| List by exam | `GET /?exam_id={examId}` | `listReportCardsByExam(int)` |

No `POST /` create route — rows are produced only by `ExamService::lockExam`
(Phase 4), never directly by a client, same reasoning SIS's
`StudentController` has no create route.

## `PromotionController` — base path `/api/v1/examination/promotions`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Promote student | `POST /` | `promoteStudent(...)` |
| Get promotion record | `GET /{id}` | `getPromotionRecord(int)` |
| List by target session | `GET /?to_session_id={sessionId}` | `listPromotionsByToSession(int)` |

## Conclusion

Every endpoint across all four Controllers is ready for implementation, on
the basis of ADR-005's resolutions. No Open item remains in this module's
own design; the items ADR-005 named as out of scope (BR-EXM-005 real
enforcement, fee-closure auto-computation, board-affiliation cross-check,
PDF rendering, `ApprovalRequest`-routed re-evaluation) are explicitly
excluded, not silently missing.
