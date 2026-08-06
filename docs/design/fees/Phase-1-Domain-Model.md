---
status: Approved (Original)
last-updated: 2026-08-06
references: Appendix-G Data Dictionary v1.0 (FEE module entities), Appendix-C v1.1 (BR-FEE-001–008), ADR-007
---

# Phase 1 — Fees Domain Model

## Scope

Per ADR-007: `Fees` (`App\Modules\Fees`) owns five entities: `FeeHead`,
`FeeStructure` (both Master), `Invoice`, `Payment`, `ScholarshipWaiver`
(all Transaction). Field lists taken directly from Appendix-G. `Invoice`
has no persisted line-item breakdown (ADR-007 §1); BR-FEE-003/007 are out
of scope (ADR-007 §3, §7); BR-FEE-004/008 are explicit-trigger, not
scheduled jobs (ADR-007 §4).

## Entity: `FeeHead` (ENT-FEE-001, table `fee_heads`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| fee_head_name | VARCHAR(50) | N | – | Unique |
| is_taxable | BOOLEAN | N | FALSE | BR-FEE-007 (stored, unused in calculations this pass — ADR-007 §7) |
| gst_rate | DECIMAL(5,2) | Y | NULL | 0–28 if present; unused this pass |

### Lifecycle

Created at session setup → Modified (rare) → Deactivated if discontinued
(soft-delete only, same reasoning as Academic's `Class`).

---

## Entity: `FeeStructure` (ENT-FEE-002, table `fee_structures`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| class_id | BIGINT UNSIGNED | N | – | FK → Academic's `classes` (cross-module, plain FK, validated via `ClassService`) |
| fee_head_id | BIGINT UNSIGNED | N | – | FK → `fee_heads` (intra-module, real FK) |
| academic_session_id | BIGINT UNSIGNED | N | – | FK → Academic's `academic_sessions` (cross-module, plain FK, validated via `AcademicSessionService`) |
| category | enum (`GENERAL`, `RTE`) | N | GENERAL | BR-FEE-005 |
| amount | DECIMAL(10,2) | N | – | Positive |

Unique constraint: `(class_id, fee_head_id, academic_session_id,
category)`. No discovered lock flag anywhere in Appendix-G's attribute
catalogue for this entity — "Update (pre-lock)" in its CRUD Operations
line names no concrete lock condition, so none is invented; `FeeStructure`
is freely editable in place, same reasoning as Academic's `Class`.

### Lifecycle

Created (versioned per session — a new `academic_session_id` value is
itself the versioning mechanism) → Active → Superseded (a later session's
row; the earlier row is retained, never deleted, for historical invoice
traceability, per Appendix-G's own lifecycle text).

---

## Entity: `Invoice` (ENT-FEE-003, table `invoices`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| invoice_no | VARCHAR(20) | N | – | Unique, system-generated (ADR-007 §10) |
| student_id | BIGINT UNSIGNED | N | – | FK → SIS's `students` (cross-module, plain FK, validated via `StudentService`) |
| academic_session_id | BIGINT UNSIGNED | N | – | FK → Academic's `academic_sessions` (cross-module, plain FK) |
| total_amount | DECIMAL(10,2) | N | – | Computed at generation (ADR-007 §1), never client-supplied |
| due_date | DATE | N | – | |
| status | enum (`UNPAID`, `PARTIALLY_PAID`, `PAID`, `DEFAULTER`, `CANCELLED`) | N | UNPAID | BR-FEE-001, BR-FEE-008 |
| is_locked | BOOLEAN | N | FALSE | Becomes `TRUE` once any `Payment` is recorded (BR-FEE-001) and never reverts (ADR-007 §9) |
| late_fee_applied | BOOLEAN | N | FALSE | Decided additive column, not in Appendix-G's literal attribute list — idempotency tracking for `applyLateFee` (BR-FEE-004), same kind of decided addition as Academic's `locked_by_closed_exam` (ADR-005 §10) |

Unique constraint: `invoice_no`. No line items (ADR-007 §1).

### Lifecycle

Created (Generated, via `InvoiceService::generateInvoice`) → `PARTIALLY_PAID`/
`PAID` (locked, BR-FEE-001) or `DEFAULTER` (`InvoiceService::
flagOverdueAsDefaulter`, BR-FEE-008) → Archived. `CANCELLED` is reachable
from `UNPAID` only (a mistaken invoice with no payment yet) — cancelling
a locked invoice is out of scope, matching ADR-007 §9's credit-note gap.

---

## Entity: `Payment` (ENT-FEE-004, table `payments`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| invoice_id | BIGINT UNSIGNED | N | – | FK → `invoices`, RESTRICT (intra-module, real FK) |
| amount_paid | DECIMAL(10,2) | N | – | Positive |
| payment_mode | enum (`ONLINE`, `CASH`, `CHEQUE`, `BANK_TRANSFER`) | N | – | |
| gateway_transaction_ref | VARCHAR(100) | Y | NULL | Unique where present (BR-FEE-006) |
| paid_at | DATETIME | N | CURRENT_TIMESTAMP | |
| status | enum (`SUCCESS`, `FAILED`, `REFUNDED`, `VOIDED`) | N | SUCCESS | BR-FEE-002 |

Unique constraint: `gateway_transaction_ref` (nullable-safe).

### Lifecycle

Created (recorded, `SUCCESS`) → Refunded/Voided (`PaymentService::
refundPayment`/`voidPayment` — role restriction not enforced, ADR-007
§8) → Archived. Does not reopen the parent `Invoice` (ADR-007 §9).

---

## Entity: `ScholarshipWaiver` (ENT-FEE-005, table `scholarship_waivers`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| student_id | BIGINT UNSIGNED | N | – | FK → SIS's `students` (cross-module, plain FK) |
| fee_head_id | BIGINT UNSIGNED | N | – | FK → `fee_heads` (intra-module, real FK) |
| waiver_type | enum (`RTE`, `MERIT`, `SIBLING`, `STAFF_WARD`) | N | – | BR-FEE-005 |
| waiver_amount | DECIMAL(10,2) | N | – | Positive |

No unique constraint (per Appendix-G — a student could plausibly have more
than one waiver reason interacting with the same fee head over time,
though this pass doesn't validate against double-counting beyond what
`generateInvoice`'s own sum naturally does).

### Lifecycle

Created (Finance Team, explicit — ADR-007 §5) → Active for the session →
Applied automatically at every subsequent `generateInvoice` call for that
student → Archived.

## Out of scope

- BR-FEE-003 Transport auto-linkage (ADR-007 §3).
- BR-FEE-007 GST computation (ADR-007 §7 — no line-item entity).
- Scheduled/cron execution of late-fee and defaulter checks (ADR-007 §4 —
  explicit-trigger only).
- `CreditNote` workflow for correcting a locked `Invoice` (ADR-007 §9).
- BR-FEE-002 role-restricted refund/void authorization (ADR-007 §8 — this
  codebase's existing, consistent RBAC posture).
