---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-011 record)
relates-to: Appendix-G ENT-SYS-006 (Document); Appendix-E FR-09, FR-20, FR-23; ADR-005 §9 (report-card PDF gap), ADR-008 §10 (payslip PDF gap), ADR-010 §8 (export gap)
---

# ADR-012: Document/PDF module scope — closing three named PDF gaps for real

## Context

Three prior ADRs each named the same underlying gap from a different
angle and deferred it: ADR-005 §9 (Examination report cards are "data
records only, no PDF — `Document`/PDF tooling don't exist"), ADR-008 §10
(HR & Payroll payslips are "data-only via `PayrollRun`'s own fields, no
`Document`/PDF"), ADR-010 §8 (Reports' Excel/PDF export is out of scope
"no Excel/PDF library exists in this codebase"). `ENT-SYS-006`
(`Document`) is the entity that was always meant to close this — a
generic, polymorphic file-metadata record (`owner_type`:
`Application`/`Student`/`Invoice`/`ReportCard`, per its own Appendix-G
attribute catalogue) — plus an actual PDF rendering capability, which
this codebase genuinely has none of yet.

This pass adds `dompdf/dompdf` (pure PHP, no native extensions, works
unmodified on shared hosting — consistent with this project's Hostinger
deployment target) as this codebase's first PDF-rendering dependency,
and builds `Document` in Administration (`Module: SYS`, joining
`User`/`Role`/`AuditLog`/`Configuration`).

Not every PDF-shaped gap this pass could touch is in scope. Three
Appendix-E requirements name document generation (FR-09 ID Card/
Certificate, FR-20 Report Card, FR-23 Invoice/Receipt); only two of the
three are taken up here, for a reason specific to each:

- **FR-20 (Report Card)** and **FR-23 (Invoice)** generate a PDF
  entirely from data this codebase already has — calculated
  grade/GPA/rank, and an already-computed `total_amount`/line items.
  Nothing external is missing.
- **FR-09 (ID Card/Certificate)** needs a school-branded template with a
  student photo — explicitly marked "Desirable, not Mandatory" in RGD
  v2.0 (Appendix-E's own Constraints field), and its own Assumptions
  field states "templates are pre-designed and approved by school
  management" — no such template or photo-upload capability exists
  anywhere in this codebase. Not implemented here — inventing a
  placeholder branding template is a design decision this ADR doesn't
  make unilaterally.
- **Payslip** (HR & Payroll) isn't a numbered FR on its own but was
  named as a gap by ADR-008 §10 directly — included, same reasoning as
  Report Card (all its data already exists on `PayrollRun`).
- **Receipt** (the second half of FR-23's output) is decided as **not a
  separate template** — a `Payment`'s "receipt" is the same rendered
  `Invoice` PDF once `status = PAID`/`PARTIALLY_PAID`, not a distinct
  document type, keeping this pass to three renderers instead of four
  for functionally identical content.

## Decision

### 1. `Document` — implemented per Appendix-G, in the Administration module

Maps directly onto its attribute catalogue: `owner_type`
(`Application`/`Student`/`Invoice`/`ReportCard`), `owner_ref_id`,
`document_type` (free-text label, e.g. `Report Card`, `Payslip`,
`Invoice`), `file_path`, `uploaded_by`, `uploaded_at`. No unique
constraint (per Appendix-G) — regenerating a document for the same
owner creates a new row, preserving history, never overwriting one.
`uploaded_by` is validated via Administration's own `UserService`.

### 2. Storage is local disk under `writable/uploads/documents/`, not cloud

Appendix-G's own Integration Mapping marks the storage backend "Client/
Product Decision Required" (local/cloud). Decided as local disk —
`writable/` is already the project's existing writable-storage
convention (cache, logs, sessions all live there, already gitignored)
and needs no new infrastructure or vendor account. `file_path` stores a
path relative to `writable/uploads/documents/`, not an absolute
filesystem path — portable across environments.

### 3. Three PDF renderers, all built from data this codebase already computes

`ReportCardService::generatePdf(int $reportCardId)`,
`PayrollRunService::generatePayslipPdf(int $payrollRunId)` (rejects
unless `status = Processed`, matching BR-HR-007's immutability — a
payslip is only generated once issued), and
`InvoiceService::generateInvoicePdf(int $invoiceId)` each render a
plain, functional HTML template through `dompdf` and create a
`Document` row pointing at the result. Templates are deliberately plain
— structured tables of the same fields each entity's own `Response` DTO
already exposes, no school branding/logo (Context — no such asset
exists to include, same reasoning FR-09 is deferred on).

### 4. FR-09 (ID Card/Certificate Generation) is out of scope

Per Context — "Desirable, not Mandatory," no branding template or
student-photo capability exists anywhere in this codebase. Not
implemented; a future pass scoping a real template/photo-upload
capability is the prerequisite, not something to invent here.

**Resolved:** `docs/ADR/ADR-023-sis-id-card-generation.md` — the user
was explicitly asked and chose a generic placeholder branding template
plus a real photo-upload capability reusing `Document`/`DocumentService`;
ID Card generation is implemented there (Certificate generation remains
deferred, no concrete content ever specified for it).

### 5. Download is a plain authenticated stream, no ownership/role gate

Matches this codebase's consistent, already-documented posture
(`PermissionChecker` has never been wired into a Controller, ADR-007
§8's standing precedent) — any authenticated user can `GET` a
`Document`'s file by `document_id`, gated only by the existing `jwtauth`
filter on the whole route group, same as every other endpoint in this
codebase.

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s Administration row
  gains `Document` (`ENT-SYS-006`) as designed; `ApprovalRequest`
  remains not yet designed (unchanged, still deferred per Stage 1).
- `docs/design/administration/Phase-8-Document-Design.md` (this ADR's
  Phase doc) proceeds on the basis of every decision above.
- `App\Modules\Examination\Services\ReportCardService`,
  `App\Modules\HrPayroll\Services\PayrollRunService`, and
  `App\Modules\Fees\Services\InvoiceService` each gain one new PDF-
  generation method in this same pass — closing ADR-005 §9, ADR-008
  §10, and FR-23's invoice half for real. Small, targeted additions to
  already-shipped code, not new module boundary violations (each calls
  `DocumentService` the same way every module already calls
  `AuditService`).
- `composer.json` gains its first PDF-rendering dependency
  (`dompdf/dompdf`) — pure PHP, no native extension requirements,
  compatible with the project's Hostinger shared-hosting deployment
  target.
- A future ID-card/certificate pass must account for FR-09 (§4) once a
  real branding template and photo-upload capability are scoped.
  **Resolved** by ADR-023 (ID Card only; Certificate remains deferred).
- A future Reports pass revisiting ADR-010 §8's Excel/PDF export gap can
  now reuse this same `dompdf` dependency and `DocumentService` pattern,
  rather than re-deciding the rendering approach from scratch.
