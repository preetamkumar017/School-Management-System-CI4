---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, ADR-007
---

# Phase 3 — Fees Service and Controller Design

## `FeeHeadService` / `FeeStructureService`

Plain CRUD (create/update/get/list), uniqueness per Phase 2, cross-module
FK validation via Academic's `ClassService`/`AcademicSessionService`. No
delete — Master data, same reasoning as every prior Master entity.

## `InvoiceService`

| Operation | Reason |
|---|---|
| `generateInvoice(GenerateInvoiceRequest): InvoiceResponse` | Resolves the student's `class_id` via SIS `Student.section_id` → Academic `SectionService` (`STUDENT_HAS_NO_SECTION` if null, ADR-007 §2); sums `FeeStructure` for `(class_id, academic_session_id, student's category)` minus matching `ScholarshipWaiver`s (ADR-007 §1); generates `invoice_no` (ADR-007 §10); `status = UNPAID`. |
| `applyLateFee(int $id): InvoiceResponse` | BR-FEE-004: adds 5% of `total_amount` (ADR-007 §4), tracked via a decided additive `late_fee_applied` boolean (not in Appendix-G's literal attribute list — needed for idempotency, same kind of decided addition as Academic's `locked_by_closed_exam`, ADR-005 §10). Rejects with `LATE_FEE_ALREADY_APPLIED` if already `true`. |
| `flagOverdueAsDefaulter(int $id): InvoiceResponse` | BR-FEE-008: `status → DEFAULTER` if `due_date` has passed and `status` not in `PAID`/`CANCELLED`. |
| `getInvoice(int $id): InvoiceResponse` | Plain read. |
| `listByStudent(int $studentId): array` | Invoice history. |

## `PaymentService`

| Operation | Reason |
|---|---|
| `recordPayment(RecordPaymentRequest): PaymentResponse` | BR-FEE-006 dup check on `gateway_transaction_ref`; rejects if `Invoice.status = CANCELLED`; updates `Invoice.status` from the cumulative `SUCCESS` sum (`PARTIALLY_PAID` if `< total_amount`, `PAID` if `≥`); sets `Invoice.is_locked = true` unconditionally (BR-FEE-001) — never unset. |
| `voidPayment(int $id, VoidRefundRequest): PaymentResponse` | `status → VOIDED`; does **not** recompute `Invoice.status`/`is_locked` (ADR-007 §9) — the invoice's paid history is a fact, not reversible bookkeeping. |
| `refundPayment(int $id, VoidRefundRequest): PaymentResponse` | `status → REFUNDED`; same non-reopening behavior as `voidPayment`. |
| `getPayment(int $id): PaymentResponse` | Plain read. |
| `listByInvoice(int $invoiceId): array` | Payment history for an invoice. |

## `ScholarshipWaiverService`

| Operation | Reason |
|---|---|
| `createWaiver(CreateScholarshipWaiverRequest): ScholarshipWaiverResponse` | Validates `student_id` (SIS), `fee_head_id` (intra-module); `waiver_amount` should not exceed the corresponding `FeeStructure.amount` for the student's class/session (BR-FEE-005's attribute-level validation rule) — checked when a matching `FeeStructure` row exists at creation time; if none exists yet (structure configured later), the check is skipped rather than blocking waiver creation. |
| `getWaiver(int $id): ScholarshipWaiverResponse` | Plain read. |
| `listByStudent(int $studentId): array` | A student's waivers. |

## Controllers — base path `/api/v1/fees/...`

| Controller | Base path | Endpoints |
|---|---|---|
| `FeeHeadController` | `/fee-heads` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` |
| `FeeStructureController` | `/fee-structures` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /?class_id&academic_session_id&category` |
| `InvoiceController` | `/invoices` | `POST /` (generate), `POST /{id}/apply-late-fee`, `POST /{id}/flag-defaulter`, `GET /{id}`, `GET /?student_id` |
| `PaymentController` | `/payments` | `POST /` (record), `POST /{id}/void`, `POST /{id}/refund`, `GET /{id}`, `GET /?invoice_id` |
| `ScholarshipWaiverController` | `/scholarship-waivers` | `POST /`, `GET /{id}`, `GET /?student_id` |

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-007's
resolutions. BR-FEE-003/007, scheduled-job automation, `CreditNote`, and
role-restricted refund/void authorization are explicitly out of scope,
not silently missing.
