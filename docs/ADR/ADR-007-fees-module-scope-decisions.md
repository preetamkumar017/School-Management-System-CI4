---
status: Accepted
date: 2026-08-06
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-06 — see ADR-004/005/006, same delegation)
relates-to: Appendix-C v1.1 (BR-FEE-001–008); Appendix-E (FR-22–26b); ADR-005 §3 (`PromotionRecord.fee_closure_confirmed` seam)
---

# ADR-007: Fees module scope — invoice-without-line-items, undesigned-module stubs, and no-scheduler-infrastructure decisions

## Context

`Fee Collection` (`ENT-FEE-001`–`005`: `FeeHead`, `FeeStructure`, `Invoice`,
`Payment`, `ScholarshipWaiver`) depends only on Academic (`Class`,
`AcademicSession`) and SIS (`Student`) per Appendix-G's FK list — both
already built, unlike Stage 6b's late-discovered Timetable/HR chain. Working
through Appendix-C's eight `BR-FEE-*` rules and Appendix-E's FR-22 through
FR-26b surfaces several items needing resolution before implementation,
per the same delegation ADR-004/005/006 already exercised.

## Decision

### 1. `Invoice` has no persisted line-item breakdown — computed, not stored

Appendix-G's `Invoice` entity has no line-item child entity and no FK to
`FeeStructure`/`FeeHead` at all — its only relationship is to `Student`,
`AcademicSession`, and (as parent) `Payment`. Its own `total_amount`
description reads "sum of line items minus waivers," describing how the
number is *derived*, not a separate stored breakdown. `InvoiceService::
generateInvoice` computes `total_amount` server-side by summing every
`FeeStructure` row matching the student's resolved `class_id` +
`academic_session_id` + `category`, minus every matching `ScholarshipWaiver.
waiver_amount` for the same student/fee-head pairs — and persists only the
resulting total, exactly matching the approved entity shape. No
`InvoiceLineItem` entity is invented.

### 2. Invoice generation requires the student to have a `section_id`

`FeeStructure` is keyed by `class_id`, but `Student` (SIS) has no
`class_id` — only `section_id`, resolved to a class via Academic's
`SectionService`. A `DRAFT` student with no section assigned has no
resolvable class and cannot be invoiced. `generateInvoice` requires
`Student.section_id` to be non-null (`STUDENT_HAS_NO_SECTION` otherwise) —
this is a precondition inherent to the data model, not a new business
rule invented here.

### 3. BR-FEE-003 (Transport Fee Auto-Linkage) is not implemented

Transport is undesigned. No automatic linkage exists. FR-23's own
"ad-hoc, one-off invoice raised manually by Finance Team for a specific
fee event" alternate flow already covers a transport fee being invoiced
manually via a `FeeHead` created for it — the automation is what's
deferred, not the capability. A future Transport module design closes
this gap, the same shape as every other undesigned-module stub this
project has made (ADR-005 §2/§3, ADR-006 §1/§9).

### 4. BR-FEE-004 (Automated Late Fee) and BR-FEE-008 (Defaulter Escalation) are explicit-trigger, not scheduled jobs

No job-scheduling infrastructure exists anywhere in this codebase — no
prior stage built one. Both rules describe a "scheduled overdue check
(daily)"; both are implemented instead as explicit Service methods
(`InvoiceService::applyLateFee`, `InvoiceService::flagOverdueAsDefaulter`)
that a caller (Finance Team via the API today; a future cron/scheduler
later) invokes directly. This is a capability/automation-trigger split,
not a missing capability — the exact same shape ADR-006 §6 used for
BR-TT-005's revision logic (implemented, just not the notification half).
The late-fee formula ("Client/Product Decision Required") is decided as a
flat **5% of `total_amount`, applied once** (idempotent — a second call on
an invoice that already has a late fee applied is rejected). The overdue
threshold (also "Client/Product Decision Required") is decided as **past
`due_date` and not fully paid** (`status` not in `PAID`/`CANCELLED`) —
no additional grace-period count, the simplest defensible reading of
"crossing the configured overdue threshold." Both are documented,
tunable defaults pending a future `Configuration` entity, joining the
five prior defaults from ADR-005/ADR-006. No reminder/notification is
dispatched (Communication is undesigned, ADR-006 §9's identical shape).

### 5. BR-FEE-005 (RTE-Quota Waiver) — automatic application, manual creation

The rule's precondition — "waived fee-head list for RTE students is
configured" — is itself "Client/Product Decision Required" with no
configuration source anywhere in the approved schema (same shape as
BR-TT-003's missing room capacity, ADR-006 §4). What **is** fully
specified is `ScholarshipWaiver` itself, as its own entity. Decided
split: a Finance Team member creates a `ScholarshipWaiver` row explicitly
(intentionally, per student/fee-head — mirroring `waiver_type` including
`RTE`, `MERIT`, `SIBLING`, `STAFF_WARD`, none of which the system could
infer on its own); `generateInvoice` then **automatically** subtracts
every matching waiver for that student, satisfying the rule's actual
operative requirement — "without manual invoice editing" — the waiver
isn't re-entered per invoice, only applied automatically once recorded
once.

### 6. BR-FEE-006 (Duplicate Payment Prevention) — implemented for real

Fully self-contained: a DB-level unique constraint on
`gateway_transaction_ref` (nullable-safe — MySQL permits multiple `NULL`s
in a unique index, so offline payments with no gateway reference are
unaffected).

### 7. BR-FEE-007 (GST Applicability) is not implemented

Decided by §1: no line-item entity exists to itemize which portion of an
invoice is GST-taxable. `FeeHead.is_taxable`/`gst_rate` are stored as
configuration (per Appendix-G's own attribute catalogue) but unused in
any invoice/receipt calculation this pass — there is nothing to attach a
computed GST amount to without inventing the line-item entity §1
explicitly declines to invent. A future receipt/PDF-generation pass
(alongside Examination's deferred report-card PDF, ADR-005 §9) would
need to resolve this together with a real line-item model.

### 8. BR-FEE-002 (Finance-Team-Only Refund/Void) is not enforced

Every prior stage's Appendix-E Role Permissions table names role
restrictions (Academic Head approvals, Teacher-only creates, etc.) that
this codebase has never enforced beyond JWT authentication —
`App\Core\RBAC\PermissionChecker` exists (built in Stage 1) but is not
wired into any Controller in any module shipped so far. This is not a
new gap specific to Fees; it is the codebase's existing, consistent
posture on granular RBAC, restated here rather than silently carried
forward unstated. `PaymentService::voidPayment`/`refundPayment` exist and
work for any authenticated caller, same as every other mutating endpoint
in this codebase today. **Since resolved, 2026-08-07: ADR-018 enforced
this specific rule via `PaymentService::PERMISSION_VOID_REFUND`, reusing
ADR-015's permission-string pattern — every other Appendix-E-named
restriction across other modules remains unenforced, unchanged by that
ADR.**

### 9. `Payment` void/refund does not reopen or re-lock `Invoice`

Per BR-FEE-001, `Invoice.is_locked` becomes `true` the moment **any**
payment is ever recorded against it, and stays `true` — voiding or
refunding that payment doesn't undo the fact a payment was recorded, so
the invoice remains locked. Appendix-G names a "credit note workflow" as
the correction path for a locked invoice but does not model a `CreditNote`
entity anywhere in its catalogue — not implemented this pass, the same
kind of bounded exclusion as Examination's `ApprovalRequest`-free
re-evaluation (ADR-005 §7). A Finance Team member wanting to correct a
locked invoice's amount today has no in-system workflow beyond manual
follow-up; a future `CreditNote` entity design would close this.

### 10. Invoice number format — decided

`sprintf('INV-%s-%05d', $year, random_int(10000, 99999))` with
retry-on-collision, identical shape to Admission's `application_reference_no`
and Examination's `admission_number` generation (ADR unstated formula,
matches Appendix-G's own example `INV-2026-04501`).

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s Fees row is updated
  to Designed, citing this ADR.
- `docs/design/fees/Phase-1` through `Phase-3` proceed on the basis of
  every decision above; none are re-derived there.
- A future Transport module design must account for §3's seam
  (auto-linkage on allocation confirm/de-allocate).
- A future Configuration module design must account for §4's two
  defaults (late-fee percentage, overdue threshold), joining the five
  priors from ADR-005/ADR-006.
- A future receipt/PDF/CreditNote design pass must account for §7 (GST
  itemization) and §9 (credit-note workflow) together — both need the
  same missing line-item-shaped entity to resolve properly.
