---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-019 record; user explicitly warned this reverses part of ADR-007 §1 and approved proceeding)
relates-to: Appendix-C BR-FEE-007 (GST Applicability Determination), FR-26a; ADR-007 §1 (invoice-without-line-items decision, reversed here), §7 (BR-FEE-007 named not implemented, resolved here), §4 (late-fee flat-percentage decision, reused unchanged); ADR-014 §1 (BR-FEE-003 route-tier FeeStructure lookup, reused unchanged)
---

# ADR-020: Fees GST line-item itemization (BR-FEE-007)

## Context

Appendix-C's BR-FEE-007 requires: "GST must be applied on a fee receipt
only for fee heads configured as taxable services," with a post-condition
that the "Receipt correctly itemizes GST only for taxable fee heads."
Stage 6c already built the precondition's storage half —
`FeeHead.is_taxable`/`gst_rate` are real, stored columns
(`2026-08-06-140001_CreateFeeHeadsTable.php`), set today by a Finance
Team member via `FeeHeadService::createFeeHead`/`updateFeeHead` — but
ADR-007 §7 explicitly left them unused in any invoice/receipt
calculation, because §1 of the same ADR had decided `Invoice` carries no
line-item breakdown, only a computed `total_amount`. Nothing needed
itemization until now; BR-FEE-007's own post-condition is the first rule
whose wording requires a persisted, queryable per-fee-head breakdown, not
just a number that happens to be correct.

## Decision

### a. ADR-007 §1 is reversed, specifically for this reason

`Invoice` gains a real line-item child entity. This is not a general
re-opening of §1's reasoning — that reasoning was correct for every BR
resolved before this one, none of which needed a breakdown. BR-FEE-007's
post-condition is worded as "itemizes," not "computes correctly"; a
single `total_amount` field cannot satisfy that wording no matter how
correct the number is. That is the entire justification for the
reversal, and it is scoped to exactly this BR.

### b. `InvoiceLineItem` — a new Fees entity, one row per matching `FeeStructure`

`invoice_line_items` (`invoice_line_item_id`, `invoice_id` FK,
`fee_head_id` FK, `base_amount` — the pre-waiver `FeeStructure.amount`,
`waiver_amount`, `taxable_amount` — post-waiver, the GST base,
`gst_rate`, `gst_amount`, `line_total`, plus the standard audit/
soft-delete columns). Both FKs are intra-module — real DB FKs.
`invoice_id` is `CASCADE`/`CASCADE` (a line item has no meaning once its
parent invoice is gone — unlike `Trip`'s RESTRICT-everything historical-
log posture, ADR-019 §2, line items are always regenerated together with
their invoice, never standalone history); `fee_head_id` is `RESTRICT`/
`RESTRICT`, matching `FeeStructure.fee_head_id`'s own FK.

### c. GST is computed on the post-waiver (net) amount — the decided default

`taxable_amount = max(0, base_amount - waiver_amount)`;
`gst_amount = is_taxable ? round(taxable_amount * gst_rate / 100, 2) : 0`;
`line_total = taxable_amount + gst_amount`. Tax-on-net-after-discount is
the standard GST practice this project defaults to absent a specific
client instruction otherwise — a waiver is a genuine reduction in what
the student owes for that fee head, not a rebate paid after tax, so the
taxable value is what remains after the waiver, exactly the same
"waiver reduces the fee-head amount before anything else is computed
from it" posture `computeTotalAmount`/`buildLineItems` already used for
the un-itemized total under ADR-007 §5. This is a default, not a
"Client/Product Decision Required" reopening — Appendix-C names the
taxable-list/rate as the open decision, not the tax base calculation
method.

### d. Route-tier fee and late fee interaction with line items

The route-tier `FeeStructure` row (ADR-014 §1, BR-TRN-005) is not a
special case here — `buildLineItems` iterates every `FeeStructure` row
`FeeStructureService::listByClassSessionCategory` returns, which already
includes the route-tier row when the student has a matching active
`TransportAllocation`; that row becomes an ordinary line item keyed to
its own `FeeHead`, taxable or not exactly like any other fee head. No
route-specific line-item logic was added.

The late fee (BR-FEE-004, `InvoiceService::applyLateFee`) remains an
adjustment to `Invoice.total_amount` outside the line-item breakdown, not
a line item of its own. Reasoning: a late fee is not tied to any
`FeeHead` — Appendix-G's `FeeHead` catalogue has no "late fee" head, and
inventing one would misrepresent it as a taxable-service fee head when
BR-FEE-004's own formula (a flat 5% of the current total, ADR-007 §4) is
a penalty, not a service charge GST attaches to. `applyLateFee`'s
existing behavior (`total_amount += lateFee`) is unchanged by this ADR;
the generated receipt PDF (§f) surfaces the late fee's presence in a
note below the line-item table rather than as a spurious row.

### e. `Invoice.total_amount` stays the single authoritative grand-total

`generateInvoice`/`recalculateForRouteChange` sum every persisted line
item's `line_total` into `total_amount`, then (for `applyLateFee`) add
the late fee on top exactly as before. Every existing consumer that only
reads `total_amount` — `hasOutstandingBalance`
(`InvoiceModel::existsOutstandingByStudentIdAndSession`, read by
`PromotionService::promoteStudent`'s `fee_closure_confirmed` check),
`flagOverdueAsDefaulter`, `PaymentService`'s payment-recording flow — is
unmodified by this ADR; the number they read is now correctly
GST-inclusive for taxable fee heads, but nothing about how they read or
interpret it changed. Verified: `InvoiceTest`'s Stage 6c waiver test
("6000 = 5000 + 2000 − 1000 waiver," no taxable fee heads in that
fixture) and Stage 10's route-tier/outstanding-balance/`PromotionTest`
suites all still pass unmodified against the new line-item-backed
`generateInvoice`.

### f. Receipt PDF itemizes per BR-FEE-007's own post-condition wording

`InvoiceService::generateInvoicePdf` renders one table row per
`InvoiceLineItem` (fee head name, base amount, waiver, GST rate/amount —
blank for non-taxable heads, line total), followed by the existing
total/status summary and, when `late_fee_applied` is true, a note that
the total includes a late fee applied outside the line items. Still
plain HTML rendered through the existing `PdfRenderer`/dompdf pipeline —
no new PDF library, matching ADR-012 §3's "receipt is the invoice PDF
once paid" precedent, now with real itemization instead of a flat total.

### g. Line items are regenerated wholesale, not patched incrementally

Both `generateInvoice` (fresh insert) and `recalculateForRouteChange`
(delete-then-reinsert via `InvoiceLineItemModel::deleteByInvoiceId`)
build the full line-item set from scratch each time, the same "recompute
from scratch" posture `computeTotalAmount` already used for the
un-itemized total before this ADR — there is no incremental diffing of
which fee heads changed. `recalculateForRouteChange`'s existing
guardrail (only untouched `UNPAID`, non-locked invoices are recalculable,
`InvoiceModel::findRecalculableByStudentId`) is unchanged and equally
protects the line-item table: a paid/locked invoice's line items are
never touched.

## Consequences

- New migration: `2026-08-07-250001_CreateInvoiceLineItemsTable.php`.
- `InvoiceLineItem` entity, `InvoiceLineItemModel`
  (`findByInvoiceId`, `deleteByInvoiceId`), `InvoiceLineItemResponse` DTO.
- `InvoiceService` gains `buildLineItems`/`persistLineItems` (private) and
  `getLineItems(int $invoiceId): array` (public); `generateInvoice` and
  `recalculateForRouteChange` now build/persist line items alongside
  `total_amount`; `generateInvoicePdf` itemizes.
- New endpoint: `GET /fees/invoices/{id}/line-items`
  (`InvoiceController::lineItems`), OpenAPI-documented
  (`InvoiceLineItemResponse` schema added to `app/Core/OpenApi/Spec.php`).
- `docs/ADR/ADR-007-fees-module-scope-decisions.md` §1 and §7's notes are
  corrected to point here, matching the precedent already set for every
  other resolved item's originating ADR (ADR-009 §14, ADR-011, etc.).
- `docs/development/School-ERP-Development-Roadmap.md`'s "Immediate next
  action" section is updated: BR-FEE-007 removed from the remaining
  items list, a new Stage 16 entry added.
- Out of scope, unchanged: GST filing/return generation, PAN/TAN
  validation (the BR names PAN/TAN Regulations only as a regulatory tag,
  not a system behavior it asks for), and ADR-007 §9's still-open
  `CreditNote` workflow for correcting a locked invoice — this ADR does
  not build a credit-note entity even though it shares the "line-item-
  shaped entity" prerequisite ADR-007's Consequences section named for
  both.
