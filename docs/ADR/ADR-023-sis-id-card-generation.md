---
status: Accepted
date: 2026-08-08
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-08 — see Context, same delegation ADR-004 through ADR-022 record; user was explicitly asked two scoping questions on branding and photo-upload and chose the low-commitment default both times)
relates-to: ADR-012 §4 (the original FR-09 deferral this ADR closes); Appendix-E FR-09; Appendix-G ENT-SYS-006 (Document)
---

# ADR-023: SIS ID card generation — closing ADR-012 §4's deferred FR-09 gap

## Context

ADR-012 §4 declined to implement FR-09 (ID Card/Certificate Generation)
because "no branding template or student-photo capability exists
anywhere in this codebase," and explicitly refused to invent either
unilaterally: "inventing a placeholder branding template is a design
decision this ADR doesn't make unilaterally." FR-09 is marked
"Desirable, not Mandatory" in RGD v2.0 (Appendix-E's own Constraints
field), and its Assumptions field states "templates are pre-designed and
approved by school management" — the reason no real template was ever
available to build against.

The user has now been explicitly asked, and answered, both of the two
scoping questions ADR-012 §4 left open:

1. **Branding** — use a **generic placeholder template**: school name as
   literal placeholder text ("School Name"), an empty bordered logo box,
   neutral styling. Explicitly not real branding, swappable later.
2. **Student photo** — build a **real upload capability**, reusing Stage
   8's `Document`/`DocumentService` file-storage capability, not a
   placeholder box only.

This is no longer an unresolved product decision; this ADR implements
both choices.

## Decision

### 1. Generic placeholder branding — user-authorized, not invented

The rendered ID card's header is a plain two-cell table: a dashed-border
empty box literally labelled "Logo," and a bold text field containing
the literal string "School Name" — never a real or invented school
identity. This mirrors the "no branding asset exists to include" posture
ADR-012 §3 already used for the Invoice/Report Card/Payslip templates,
now made visually explicit (a labelled placeholder box) rather than
simply omitted, since an ID card is specifically the artifact branding
would appear on. No school name, logo, or color scheme was invented —
the two literal placeholder strings above are the entirety of the
"branding" on this template, and are meant to be replaced once a real
template is supplied (FR-09's own Assumptions field).

### 2. Student photo — a real upload capability, reusing `Document`/`DocumentService`

`students` gains an additive nullable `photo_document_id` (`BIGINT
UNSIGNED NULL`, FK to `documents.document_id`, `ON DELETE SET NULL`) —
the identical additive-FK, `SET NULL`-on-delete shape as
`routes.driver_id`/`routes.vehicle_id` (ADR-019 §3/ADR-009 §8). A
Student can exist with no photo; deleting the underlying `Document` must
not cascade-delete the `Student`.

`DocumentService::store()` was previously PDF-only — it hardcoded a
`.pdf` extension on every stored file. It is generalized, not
duplicated: a new `string $fileExtension = 'pdf'` trailing parameter
(and the method's `$pdfBytes` parameter is renamed `$fileBytes` since it
is no longer PDF-specific) lets the file name/extension vary per call,
while every pre-existing call site (`InvoiceService::generateInvoicePdf`,
`PayrollRunService::generatePayslipPdf`,
`ReportCardService::generatePdf`) is unchanged — the default keeps them
emitting `.pdf` exactly as before. A parallel sibling method was
considered and rejected: the method body (directory creation, file
write, `Document` row insert, audit record) is identical regardless of
file type: only the extension differs, so branching that into a second
near-duplicate method would violate this codebase's existing
DRY/one-Service-method-per-shape posture for no benefit.

`StudentService::uploadPhoto(int $studentId, UploadStudentPhotoRequest
$request)` validates the extension is one of `jpg`/`jpeg`/`png`
(`BusinessRuleException STUDENT_PHOTO_INVALID_EXTENSION` otherwise),
decodes the base64 payload (`STUDENT_PHOTO_INVALID_DATA` if it isn't
valid base64/is empty), stores it via
`DocumentService::store('Student', $studentId, 'Student Photo',
$imageBytes, $userId, $extension)`, then repoints
`Student.photo_document_id` at the new `Document` row. Regenerating a
photo creates a new `Document` row rather than overwriting the old file
on disk — matching `Document`'s own no-unique-constraint,
preserve-history rule (Appendix-G, ADR-012 §1) — the previous row is
simply no longer referenced, not deleted.

**Endpoint shape — JSON base64, not multipart.** The task's own framing
allowed either `IncomingRequest::getFile()` (multipart) or a JSON body;
this codebase's entire existing API surface is JSON-only (every
Controller reads `$this->request->getJSON(true)`, and no
`getFile()`/multipart endpoint exists anywhere in `app/`). Introducing
the one and only multipart endpoint in the codebase, with its own
distinct request-parsing path and feature-test fixture story, is a
bigger deviation than asking the caller to base64-encode the image
client-side. `POST /sis/students/{id}/photo` therefore takes
`{"image_base64": "...", "extension": "jpg"}` — consistent with every
other write endpoint in this codebase, and trivially testable with
`getJSON(true)`/`withBody()` like every existing Feature test.

### 3. ID card fields — only data `Student` genuinely has

`StudentService::generateIdCardPdf(int $studentId): DocumentResponse`
renders: student name (`full_name`), admission number
(`admission_number`), class/section (resolved via
`Section.section_id -> Academic SectionService -> ClassService`, the
identical resolution `InvoiceService::generateInvoice` already performs
— "Not Assigned" if `section_id` is null), the current academic session
name (`AcademicSessionService::getCurrentActiveSession()`, "N/A" if
none is active), and the photo — the real uploaded image embedded as a
`data:` URI (read via `DocumentService::getAbsolutePath()`,
base64-encoded inline so dompdf, which has `isRemoteEnabled` disabled
per `PdfRenderer`, can render it without a filesystem/network fetch) if
`photo_document_id` is set, else a bordered "Photo" placeholder box. No
field beyond what `Student`/`Section`/`AcademicClass`/`AcademicSession`
already expose was invented (e.g. no blood group, no address — `Student`
has no such fields).

Rendering reuses the existing `App\Core\Pdf\PdfRenderer` (dompdf)
exactly as the other three PDF generators do — no new PDF library. The
result is stored via `DocumentService::store('Student', $studentId,
'IdCard', $pdfBytes, $userId)` — `document_type = 'IdCard'`, a plain new
string value, consistent with `document_type` being free-text
(`VARCHAR(50)`, no enum) exactly as `'Invoice'`/`'Report Card'`/
`'Payslip'` already are.

### 4. Both endpoints live on `StudentController`, not a new Controller

ID card generation and photo upload are both single, small operations on
a `Student` — the same shape as `sectionTransfer`/`changeStatus`
already on `StudentController`, not a new bounded concept warranting its
own Controller/route group. `POST /sis/students/{id}/photo`,
`GET /sis/students/{id}/id-card`.

### 5. Certificate generation remains out of scope

FR-09 names "ID Card/Certificate" together, but neither Appendix-C nor
Appendix-E gives any concrete Certificate content/field specification
beyond the name — no template, no fields, no BR. Building a Certificate
generator would mean inventing its entire content unilaterally, exactly
what ADR-012 §4 already refused to do for ID cards before this pass'
explicit authorization. ID Card alone satisfies everything concretely
specified; Certificate generation remains a deferred item for a future
pass once real Certificate content is scoped.

### 6. Still "Desirable, not Mandatory" — no branding-compliance claim

This closes FR-09 as a real, working, testable capability (upload a
photo, generate a downloadable ID-card PDF), but makes no claim of
actual school-branding compliance — no real template was ever supplied.
The rendered output is deliberately, visibly generic, per §1.

## Consequences

- New migration `2026-08-08-100001_AddPhotoDocumentIdToStudentsTable.php`
  — additive nullable `students.photo_document_id`, FK to
  `documents.document_id`, `SET NULL` on delete.
- `App\Modules\Administration\Services\DocumentService::store()` gains a
  trailing `string $fileExtension = 'pdf'` parameter; `$pdfBytes` renamed
  `$fileBytes`. All three pre-existing call sites unchanged.
- `App\Modules\Sis\Entities\Student` gains `photo_document_id`;
  `StudentModel::$allowedFields` and `StudentResponse` both updated to
  match.
- New: `App\Modules\Sis\DTOs\UploadStudentPhotoRequest`;
  `StudentService::uploadPhoto()`/`generateIdCardPdf()`;
  `StudentController::uploadPhoto()`/`generateIdCard()`; routes
  `POST /sis/students/{id}/photo`, `GET /sis/students/{id}/id-card`.
- `StudentService`'s constructor gains `DocumentService`/`PdfRenderer`
  dependencies; `Config\Services::studentService()` updated to match.
- New tests: `tests/Feature/Sis/StudentPhotoTest.php`,
  `tests/Feature/Sis/StudentIdCardTest.php`.
- `docs/ADR/ADR-012-document-pdf-module-scope-decisions.md` §4's FR-09
  bullet is updated to point here, matching the precedent set for every
  other resolved item's originating ADR.
- `docs/development/School-ERP-Development-Roadmap.md`'s "Immediate next
  action" section is updated: the FR-09 item is removed (the last item
  on the original follow-up list), a new dated Stage 19 entry is added,
  the passing-test count is updated, and the closing summary is revised
  to state plainly that nothing remains outstanding except genuinely
  blocked/no-further-work items (e.g. BR-TRN-003 GPS live-tracking,
  which stays out of scope for lack of hardware/vendor).
