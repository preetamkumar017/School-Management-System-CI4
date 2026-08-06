---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, ADR-007
---

# Phase 2 — Fees Model and DTO Design

Convention: `FeeHead`/`FeeStructure` are Master data (no pagination);
`Invoice`/`Payment`/`ScholarshipWaiver` are Transaction data — this pass
exposes them via scoped listings (by student/invoice), not a bulk
paginated index, since no "list all invoices" screen was scoped.

## `FeeHeadModel`

| Method | Purpose |
|---|---|
| `findByName(string $value): ?FeeHead` / `existsByName(string): bool` / `...ExceptId(...)` | Business-key uniqueness |

## `FeeStructureModel`

| Method | Purpose |
|---|---|
| `existsByClassHeadSessionCategory(int, int, int, string): bool` / `...ExceptId(...)` | Uniqueness |
| `findByClassSessionCategory(int $classId, int $academicSessionId, string $category): array` | The set summed by `generateInvoice` (ADR-007 §1) |

## `InvoiceModel`

| Method | Purpose |
|---|---|
| `existsByInvoiceNo(string): bool` | Reference-number generation collision check |
| `findByStudentId(int $studentId): array` | Student's invoice history |
| `findOverdueUnpaid(string $asOfDate): array` | Input to `flagOverdueAsDefaulter` (BR-FEE-008) — `due_date < asOfDate` and `status` not in `PAID`/`CANCELLED` |

## `PaymentModel`

| Method | Purpose |
|---|---|
| `existsByGatewayTransactionRef(string): bool` | BR-FEE-006 |
| `findByInvoiceId(int $invoiceId): array` | Invoice's payment history — sum drives `Invoice.status` (`UNPAID`/`PARTIALLY_PAID`/`PAID`) |
| `sumSuccessfulByInvoiceId(int $invoiceId): float` | Cumulative paid amount, `SUCCESS` status only |

## `ScholarshipWaiverModel`

| Method | Purpose |
|---|---|
| `findByStudentId(int $studentId): array` | A student's active waivers |
| `findByStudentIdAndFeeHeadIds(int $studentId, array $feeHeadIds): array` | The subset relevant to a specific invoice's fee heads, input to `generateInvoice`'s subtraction (ADR-007 §1) |

## DTOs

`CreateFeeHeadRequest`/`UpdateFeeHeadRequest`: `fee_head_name`,
`is_taxable`, `gst_rate` (required if `is_taxable`). `FeeHeadResponse`:
`fee_head_id`, `fee_head_name`, `is_taxable`, `gst_rate`.

`CreateFeeStructureRequest`/`UpdateFeeStructureRequest`: `class_id`,
`fee_head_id`, `academic_session_id`, `category`, `amount` (all four FKs
immutable post-creation on Update — same reasoning as every other
module's create-only FK fields; only `amount` is updatable).
`FeeStructureResponse`: `fee_structure_id`, `class_id`, `fee_head_id`,
`academic_session_id`, `category`, `amount`.

`GenerateInvoiceRequest`: `student_id`, `academic_session_id`, `due_date`.
`InvoiceResponse`: `invoice_id`, `invoice_no`, `student_id`,
`academic_session_id`, `total_amount`, `due_date`, `status`, `is_locked`.

`RecordPaymentRequest`: `invoice_id`, `amount_paid`, `payment_mode`,
`gateway_transaction_ref` (optional). `VoidRefundRequest`: `reason`
(required, logged via `AuditLog::ACTION_OVERRIDE`, ADR-007 §8's posture
notwithstanding — the *action* is still audited even though the *role*
isn't gate-checked). `PaymentResponse`: `payment_id`, `invoice_id`,
`amount_paid`, `payment_mode`, `gateway_transaction_ref`, `paid_at`,
`status`.

`CreateScholarshipWaiverRequest`: `student_id`, `fee_head_id`,
`waiver_type`, `waiver_amount`. `ScholarshipWaiverResponse`:
`scholarship_waiver_id`, `student_id`, `fee_head_id`, `waiver_type`,
`waiver_amount`.
