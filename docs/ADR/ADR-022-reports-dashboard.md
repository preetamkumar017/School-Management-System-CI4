---
status: Accepted
date: 2026-08-08
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-08 — see Context, same delegation ADR-004 through ADR-021 record; user explicitly chose four report areas — Fee collection, Attendance, Admissions funnel, Academic performance — plus Excel+PDF export, Excel via a new phpoffice/phpspreadsheet dependency, PDF reusing Stage 8's dompdf/DocumentService pattern)
relates-to: ADR-010 §7/§8 (the original "no aggregate methods yet" Reports deferral, resolved here for four specific areas); ADR-012 (Document/PDF module, PdfRenderer reused as-is); Appendix-E FR-40/41/42, Appendix-C BR-RPT-001-005 (still largely out of scope — see Consequences)
---

# ADR-022: Reports dashboard — four report areas, PDF/Excel export

## Context

ADR-010 §7/§8 deliberately left `ReportsService` as a single
`getSummary()` method composing master-data counts from ten already-
shipped modules' own `list*()` methods — no aggregate query (sum-all,
percentage, group-by) existed anywhere, because no real dashboard
requirement had been scoped yet, and adding speculative aggregate
methods to five modules "just to make a Reports dashboard look fuller"
was explicitly refused (§8, citing ADR-009 §13's precedent against
unilateral cross-module scope expansion). §8 also named the intended
resolution path: *"a future dedicated Reports pass, once real dashboard
requirements are scoped, is the right place to add genuine aggregate
query methods to the specific source modules that need them."*

The user was asked what they wanted from a Reports pass and explicitly
chose four report areas — Fee collection summary, Attendance overview,
Admissions funnel, Academic performance — plus Excel+PDF export for
each, Excel via a new `phpoffice/phpspreadsheet` dependency (none
existed, ADR-010 §8), PDF reusing Stage 8's existing
`PdfRenderer`/dompdf pattern (`InvoiceService::generateInvoicePdf`,
`ReportCardService::generatePdf`). This ADR is that dedicated pass.

## Decision

### 1. Four report areas, each with exactly the aggregate methods it needs — no speculative extras

Per §8's own permission ("once real requirements are scoped"), each
area gets new aggregate query methods added only to its **owning**
module, only for what that specific area needs:

- **Fee collection summary** (`ReportsService::getFeeCollectionSummary`)
  — owning module Fees. `InvoiceModel` gains
  `sumOutstandingBySession()`, `sumOutstandingByClassForSession()`,
  `countDefaultersBySession()` (BR-FEE-008's existing `DEFAULTER` status,
  just counted); `PaymentModel` gains `sumSuccessfulByInvoiceSession()`,
  `sumSuccessfulByClassForSession()`. Class breakdown resolves
  `Student.section_id -> Section.class_id`, the identical join
  `InvoiceService::generateInvoice` already performs — not a new
  resolution. `InvoiceService`/`PaymentService` each gain one thin
  wrapper method (`getOutstandingSummaryForSession`/
  `getCollectedSummaryForSession`) so Reports composes over the Service
  layer, never the Model, matching ADR-010 §7's unreversed rule.
- **Attendance overview** (`getAttendanceOverview`) — owning module
  Attendance. `AttendanceRecordModel` gains `countStatesForRange()`,
  `countStatesForRangeGroupedByClass()`,
  `countStatesForRangeGroupedByStudent()` — one method, three grouping
  shapes of the same present/total count, all reusing the identical
  PRESENT-or-LATE-counts-as-present definition
  `AttendanceService::calculateAttendancePercentage` (FR-13) already
  established. `AttendanceService` gains one wrapper,
  `getAttendanceOverviewData()`. The below-threshold student list
  reuses `attendance.exam_eligibility_min_percentage` — the exact
  `Configuration` key ADR-006 §11/ADR-011 already introduced — not a
  new key.
- **Admissions funnel** (`getAdmissionsFunnel`) — owning module
  Admission. `Application` has no `academic_session_id` of its own
  (re-verified against Appendix-G); "this session's applications" is
  resolved via the classes that have a `SeatAllocation` for that
  session — the identical class+session resolution
  `ApplicationService::confirmEnrollment` already uses, not a new
  concept. `ApplicationModel` gains one method,
  `countGroupedByStatusForClassIds()`; `SeatAllocationModel` gains one
  method, `findByAcademicSessionId()` (a plain listing, not an
  aggregate — occupancy is just `seats_filled`/`total_capacity`, fields
  `SeatAllocation` already has). `ApplicationService`/
  `SeatAllocationService` each gain one thin wrapper.
- **Academic performance** (`getAcademicPerformance`) — owning module
  Examination. **No new aggregate method was needed at all.**
  `ReportCardService::listReportCardsByExam()` already existed and
  already exposes everything required (`gpa`, `class_rank` per
  student); average GPA, pass/fail counts, and rank distribution are
  all computed in-memory over that existing list, in `ReportsService`
  itself. This is the most conservative possible reading of §8's
  permission: zero speculative methods where the existing Service
  surface already suffices.

Every method above answers one, and only one, of these four areas —
none is a general-purpose "sum anything" or "group by anything" utility
added preemptively.

### 2. GPA/class-rank/attendance-threshold/`total_amount` are reused verbatim, never recomputed

`ReportsService::getAcademicPerformance` reads `ReportCard.gpa` and
`ReportCard.class_rank` exactly as
`ExamService::recalculateReportCards` already computed and stored them
(Stage 6a's decided grade-point-per-subject-averaged formula and
standard-competition "1224" rank convention, ADR-005 §5/§7) — Reports
never re-derives a GPA from `MarksRecord` rows itself.
`getAttendanceOverview` reuses `attendance.exam_eligibility_min_percentage`
and the PRESENT-or-LATE present-count definition verbatim.
`getFeeCollectionSummary`'s outstanding figure is `Invoice.total_amount`
(computed once, at generation time, by `InvoiceService::generateInvoice`
— ADR-007 §1/ADR-020) minus `SUCCESS` payments — never a second,
independently-computed total. This keeps Reports a **pure composition
layer** exactly as ADR-010 §7 decided; this ADR lifts only §7/§8's "no
aggregate methods yet" restraint, for these four areas, and does not
reverse §7's "no entity, no independent computation" architecture.

### 3. Academic performance's pass/fail threshold — a documented, Reports-only interpretation

No pass/fail concept exists anywhere on `GradingScheme`/`ReportCard`
(re-verified: `GradingScheme.grade_band_json` is an arbitrary
scheme-defined `{grade: "min-max"}` map with no reserved "failing"
grade name). Rather than inventing a new field on `GradingScheme` or
`ReportCard` from a Reports-module design pass — exactly the kind of
unapproved cross-module schema expansion ADR-007 §7/ADR-009 §14 already
refused — `getAcademicPerformance` uses a documented default: GPA ≥ 4.0
counts as "pass." This is consistent with
`ExamService::recalculateReportCards`'s own grade-point formula
(`grade_point = percentage / 10`), so GPA 4.0 corresponds to the 40%
mark. This is a read-time interpretation of an already-stored number,
not a new column, not a different GPA computation, and not binding on
any other module — a future Examination design pass remains free to
add a real `is_pass`/pass-percentage concept to `GradingScheme` with
its own BR justification.

### 4. `phpoffice/phpspreadsheet` — the first Excel dependency in this codebase

`composer require phpoffice/phpspreadsheet:^3.5` (installed: 3.10.7).
No prior Excel-generation capability existed anywhere in
`composer.json` (confirmed absent, ADR-010 §8 Context item (b)).
PHP 8.2-compatible; installs and removes cleanly with no conflicts
against any existing dependency (`dompdf/dompdf`, `codeigniter4/framework`,
etc. all remain at their existing constraints). `App\Core\Excel\ExcelRenderer`
is a stateless wrapper (`render(string $sheetTitle, array $headers, array $rows): string`)
mirroring `App\Core\Pdf\PdfRenderer`'s exact shape — the one place any
Service touches the PhpSpreadsheet API directly, registered via
`Config\Services::excelRenderer()` alongside the existing `pdfRenderer()`.
One sheet per export call, one header row, no styling framework — the
same "plain table, no framework" posture the PDF exports already use.

### 5. PDF export reuses `PdfRenderer`/dompdf exactly — but is not persisted via `DocumentService`

`ReportsService::renderPdf()` builds the identical plain-HTML-table
markup `InvoiceService::generateInvoicePdf`/`ReportCardService::generatePdf`
already build, and renders it through the same `PdfRenderer::render()`
dompdf call — no new PDF library, no new rendering path. Unlike those
two, the bytes are **not** persisted via `DocumentService::store()`:
`Document.owner_type`/`owner_ref_id` require a real owning entity/row,
and Reports still has none (ADR-010 §7, unreversed by this ADR) —
inventing a synthetic "Reports" owner type solely to satisfy
`DocumentService`'s shape would be a bigger deviation than skipping
storage. Report exports are point-in-time and trivially regenerable
from the same query parameters, so there is no retention requirement
they need to satisfy. The Controller streams the bytes straight back
via `$this->response->download($filename, $bytes, true)` — the same
`ResponseTrait::download()` method `DocumentController::download()`
already uses for a real stored file, just called with an in-memory
binary rather than a file path (a supported first-class call shape of
that method, not a new response mechanism).

### 6. Excel export — same shape as PDF export

`GET /reports/{area}/excel` mirrors `/reports/{area}/pdf` exactly:
`ReportsService::renderExcel()` calls `ExcelRenderer::render()`,
streamed back the same way, same non-persistence reasoning as §5.

### 7. New endpoints — additive, `GET /reports/summary` unchanged

```
GET /reports/fee-collection?academic_session_id=
GET /reports/fee-collection/pdf?academic_session_id=
GET /reports/fee-collection/excel?academic_session_id=
GET /reports/attendance-overview?from_date=&to_date=
GET /reports/attendance-overview/pdf?from_date=&to_date=
GET /reports/attendance-overview/excel?from_date=&to_date=
GET /reports/admissions-funnel?academic_session_id=
GET /reports/admissions-funnel/pdf?academic_session_id=
GET /reports/admissions-funnel/excel?academic_session_id=
GET /reports/academic-performance?exam_id=
GET /reports/academic-performance/pdf?exam_id=
GET /reports/academic-performance/excel?exam_id=
```

`GET /reports/summary` and `ReportsService::getSummary()` are
byte-for-byte unchanged — this ADR is additive only.

## Consequences

- New: `App\Core\Excel\ExcelRenderer`; four Reports DTOs
  (`FeeCollectionSummaryResponse`, `AttendanceOverviewResponse`,
  `AdmissionsFunnelResponse`, `AcademicPerformanceResponse`).
- `ReportsService` gains four public methods plus `renderPdf()`/
  `renderExcel()`; constructor now takes `PdfRenderer`/`ExcelRenderer`.
- `ReportsController` gains twelve endpoints; `Config\Services` gains
  `excelRenderer()` and updates `reportsService()`'s construction.
- New aggregate/listing methods, by owning module:
  - Fees: `InvoiceModel::sumOutstandingBySession/sumOutstandingByClassForSession/countDefaultersBySession`;
    `PaymentModel::sumSuccessfulByInvoiceSession/sumSuccessfulByClassForSession`;
    `InvoiceService::getOutstandingSummaryForSession`;
    `PaymentService::getCollectedSummaryForSession`.
  - Attendance: `AttendanceRecordModel::countStatesForRange/countStatesForRangeGroupedByClass/countStatesForRangeGroupedByStudent`;
    `AttendanceService::getAttendanceOverviewData`.
  - Admission: `ApplicationModel::countGroupedByStatusForClassIds`;
    `SeatAllocationModel::findByAcademicSessionId`;
    `ApplicationService::getStatusCountsForClassIds`;
    `SeatAllocationService::listForAcademicSession`.
  - Examination: none — `ReportCardService::listReportCardsByExam` already sufficed.
- `composer.json` gains `phpoffice/phpspreadsheet:^3.5`.
- New tests: `tests/Feature/Reports/{FeeCollectionSummaryTest,AttendanceOverviewTest,AdmissionsFunnelTest,AcademicPerformanceTest}.php`,
  each asserting exact computed figures against known fixture data, plus
  a PDF-export and an Excel-export test per area (magic-byte checks:
  `%PDF` / `PK\x03\x04`). `tests/_support/Reports/ReportsExportAssertions.php`
  is a small shared trait for extracting `DownloadResponse`'s streamed
  bytes in a feature test.
- `docs/ADR/ADR-010-communication-and-reports-scope-decisions.md` §7/§8's
  notes are corrected to point here, matching the precedent set for
  every other resolved item's originating ADR.
- `docs/development/School-ERP-Development-Roadmap.md`'s "Immediate next
  action" section is updated: the Reports dashboard item is removed, a
  new dated Stage 18 entry is added, the passing-test count is updated.
- Out of scope, unchanged: FR-40's role-scoped dashboard widget
  visibility, FR-41's ad-hoc field-selection report builder, FR-42's
  trend/historical analytics, BR-RPT-001 (export role-gating —
  `PermissionChecker` still isn't wired into any Controller, ADR-007 §8's
  precedent), BR-RPT-002/003 (provisional labelling, versioning). These
  all still need infrastructure (a field/report authorization
  `Configuration` model, historical snapshotting) this pass does not
  add — a future Reports pass remains the right place for them, per
  ADR-010 §8's own reasoning, now narrowed further to exactly these
  remaining items.
