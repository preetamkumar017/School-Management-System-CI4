---
status: Approved (Original)
last-updated: 2026-08-07
references: Appendix-G ENT-SYS-006, Appendix-E FR-09/FR-20/FR-23, ADR-012
---

# Phase 8 — Document Entity, PDF Rendering, and Controller Design

Combined into one doc, same convention as Phase 7 (Configuration) — a
small, focused addition to Administration.

## Entity: `Document` (ENT-SYS-006, table `documents`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| owner_type | enum (`Application`, `Student`, `Invoice`, `ReportCard`, `PayrollRun`) | N | – | `PayrollRun` added to Appendix-G's list — payslips are a named gap (ADR-008 §10) this pass closes, and need an owner type |
| owner_ref_id | BIGINT UNSIGNED | N | – | Polymorphic; not FK-validated against the owning table (the owning Service already validated the record's existence when it triggered generation) |
| document_type | VARCHAR(50) | N | – | Free text, e.g. `Report Card`, `Payslip`, `Invoice` |
| file_path | VARCHAR(500) | N | – | Relative to `writable/uploads/documents/` (ADR-012 §2) |
| uploaded_by | BIGINT UNSIGNED | N | – | FK → Administration's `users` (cross-module, plain FK, validated via `UserService`) |
| uploaded_at | DATETIME | N | CURRENT_TIMESTAMP | |

No unique constraint (per Appendix-G) — regeneration creates a new row.

### Lifecycle

Created on generation → Active → Archived per data retention policy (no
separate archival job exists; soft-delete is the retention mechanism,
same as every other entity in this codebase).

## `DocumentModel`

| Method | Purpose |
|---|---|
| `findByOwner(string $ownerType, int $ownerRefId): array` | A record's document history |

## DTOs

`DocumentResponse`: `document_id`, `owner_type`, `owner_ref_id`,
`document_type`, `file_path`, `uploaded_by`, `uploaded_at`.

## `DocumentService`

| Operation | Reason |
|---|---|
| `store(string $ownerType, int $ownerRefId, string $documentType, string $absoluteFilePath, int $uploadedBy): DocumentResponse` | Moves a rendered PDF from a temp path into `writable/uploads/documents/{ownerType}/`, records the relative `file_path`. Called only by the three PDF-generating Services (ADR-012 §3), not exposed as a public "create" endpoint — Appendix-G's own Lifecycle line is "Created on upload/generation," and generation is always triggered from the owning module. |
| `getDocument(int $id): DocumentResponse` | Plain read. |
| `getAbsolutePath(int $id): string` | Resolves a `Document`'s `file_path` back to an absolute path, for the download Controller action to stream. |
| `listByOwner(string $ownerType, int $ownerRefId): array` | A record's document history. |

## `PdfRenderer` (`App\Core\Pdf`, not a Service — a stateless helper)

Wraps `dompdf/dompdf`: `render(string $html): string` (returns raw PDF
bytes), used identically by all three generating Services — no
per-module PDF-library coupling.

## Service additions (ADR-012 §3)

| Service | New method | Template |
|---|---|---|
| `ReportCardService` | `generatePdf(int $reportCardId): DocumentResponse` | Student/exam header, subject/marks/grade table, GPA, class rank |
| `PayrollRunService` | `generatePayslipPdf(int $payrollRunId): DocumentResponse` | Rejects `PAYROLL_RUN_NOT_PROCESSED` unless `status = Processed`. Employee/period header, gross pay, deductions breakdown, net pay |
| `InvoiceService` | `generateInvoicePdf(int $invoiceId): DocumentResponse` | Student/session header, total amount, due date, status — doubles as the "receipt" once `status` is `PAID`/`PARTIALLY_PAID` (ADR-012 Context — no separate receipt template) |

## Controller — base path `/api/v1/administration/documents`

| Endpoint | Purpose |
|---|---|
| `GET /{id}` | `DocumentResponse` metadata. |
| `GET /{id}/download` | Streams the file (`Content-Type: application/pdf`, `Content-Disposition: attachment`). No ownership/role gate beyond `jwtauth` (ADR-012 §5). |
| `GET /?owner_type=&owner_ref_id=` | A record's document history. |

Generation itself is triggered from each owning module's own Controller,
not `DocumentController` — e.g. `POST /api/v1/examination/report-cards/
{id}/generate-pdf`, `POST /api/v1/hr-payroll/payroll-runs/{id}/
generate-payslip`, `POST /api/v1/fees/invoices/{id}/generate-pdf`.

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-012's
resolutions. FR-09 (ID Card/Certificate) is explicitly out of scope, not
silently missing.
