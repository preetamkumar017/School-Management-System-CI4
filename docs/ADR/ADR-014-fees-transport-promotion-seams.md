---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-013 record)
relates-to: Appendix-C BR-FEE-003, BR-TRN-005, BR-SIS-001; ADR-007 §3 (original Transport Fee Auto-Linkage deferral); ADR-009 §13 (original Route Change Fee Recalculation deferral); ADR-005 §3 (original fee_closure_confirmed deferral)
---

# ADR-014: Fees/Transport route-tier linkage, and Examination's fee_closure_confirmed — closing two cross-module seams

## Context

Two deferrals from earlier ADRs were left open specifically because a
dependency didn't exist yet at the time; both dependencies (Transport,
Fees) now exist (Stage 6e, Stage 6c), so this is the unblocking pass —
same shape as ADR-013 unblocking BR-TT-004/FR-16 once
`StaffAttendanceRecord` existed.

- **BR-FEE-003 / BR-TRN-005 (ADR-007 §3, ADR-009 §13)**: a student's
  transport fee should automatically follow their route's fee tier, and
  changing a student's route should recalculate that fee. FR-23's manual
  ad-hoc `FeeHead` + `FeeStructure` path already covers the case where
  staff configure a transport charge by hand; what's missing is the
  *automatic* link from `TransportAllocation.route_id` to the right
  `FeeStructure` row at invoice time, and the trigger to recompute an
  already-issued but unpaid invoice when the route changes.
- **BR-SIS-001 (ADR-005 §3)**: `PromotionRecord.fee_closure_confirmed`
  is currently a caller-supplied boolean because Fees didn't exist when
  Examination shipped. Fees now owns `Invoice`, which is the real source
  of truth for whether a student's fees are settled.

Per the standing delegation established in ADR-004 through ADR-013,
this ADR resolves both rather than leaving them pending.

## Decision

### 1. Route-tier fee linkage: additive nullable `FeeStructure.route_id`, resolved at generation time; recalculation is Transport-triggered

`FeeStructure` gains a nullable `route_id` (additive column, same shape
as Academic's `locked_by_closed_exam`) — a cross-module reference to
Transport's `routes` table, no DB-level FK (same convention `class_id`/
`academic_session_id` already use for Academic). `route_id IS NULL`
means "applies regardless of route" (every existing row); a real value
scopes the row to students with an *active* `TransportAllocation` on
that specific route. The unique key becomes `(class_id, fee_head_id,
academic_session_id, category, route_id)` so a route-tier row can
coexist with the base row for the same class/session/category.

Two distinct interaction shapes, deliberately different directions:

- **At invoice generation, Fees reads Transport.**
  `InvoiceService::generateInvoice()` already reaches into SIS
  (`StudentService`), Academic (`AcademicSessionService`,
  `SectionService`) to gather facts needed for *this specific request*
  — it's a computed-total operation, not a live/polling dependency. This
  ADR extends that same pattern one module further:
  `TransportAllocationService::getActiveAllocationForStudent()` is
  queried for the student's route, and `FeeStructureModel::
  findByClassSessionCategory()` now takes an optional `route_id` that
  folds in the matching route-tier row (if any) alongside the base
  rows. This is read-only, request-scoped, and symmetric with the
  existing SIS/Academic calls — it does not create a live coupling.
- **On route change, Transport pushes into Fees.** This is the
  opposite direction on purpose. `TransportAllocationService::
  changeRoute()` (new — BR-TRN-005 didn't have a route-change mutator
  at all before this) is the only place that knows *when* a route
  actually changed. Per BR-FEE-004/008's established precedent (no
  scheduler/cron — explicit-trigger Service methods only), it calls a
  new explicit-trigger method, `InvoiceService::
  recalculateForRouteChange($studentId, $newRouteId)`, immediately
  after committing the route change. Fees never watches or polls
  Transport for this — Transport, the module that owns the triggering
  event, pushes the fact forward. This mirrors the established
  dependent-pushes-into-owner shape (Attendance pushing into HR's
  `attendance_closures`; Academic writing `locked_by_closed_exam` when
  Examination closes a session), just with Transport as the pushing
  module and Fees as the module being updated this time.

  These two arms don't form a Fees⇄Transport cycle in the problematic
  sense — one is Fees reading Transport's current state, the other is
  Transport telling Fees about an event that already happened. Neither
  arm depends on the other executing.

`recalculateForRouteChange()` only touches invoices
`InvoiceModel::findRecalculableByStudentId()` returns — status
`UNPAID` and not `is_locked`. A `PARTIALLY_PAID`, `PAID`, `DEFAULTER`,
`CANCELLED`, or locked invoice is left untouched; recomputing a total
underneath a payment already recorded against it would corrupt the
Payment/Invoice relationship, and that is explicitly out of scope here
— a genuinely paid-against invoice needing adjustment is a manual
finance operation, not something this seam should do silently.

`FeeStructureService::createFeeStructure()` validates a supplied
`route_id` against `RouteService::getRoute()` — the same
cross-module-existence-check shape every other `*_id` in this method
already uses (`ClassService`, `AcademicSessionService`, `FeeHeadModel`).

### 2. `fee_closure_confirmed`: fully system-computed, no caller override

Fees now exists, so `PromotionService::promoteStudent()` computes
`fee_closure_confirmed` itself instead of trusting the caller — the
exact same treatment `academic_closure_confirmed` already got in
ADR-005 §3 (`$fromSession->status === 'CLOSED'`, never caller input).
This ADR makes both closure flags system-truth for the first time.

**Computation**: "fee closure" means no *outstanding* `Invoice` for
that student **for the `from_session_id` being closed out of** —
`InvoiceModel::existsOutstandingByStudentIdAndSession()` treats
`UNPAID`, `PARTIALLY_PAID`, and `DEFAULTER` as outstanding; `PAID` and
`CANCELLED` are not. Scoping to `from_session_id` (not "any session
ever") matches BR-SIS-001's framing — promotion gates on closing out
*this* session, not a lifetime balance; a family that already settled
every invoice for the session being left should not be blocked by an
unrelated fee from a different session context still working through
non-fee-related closure.

**No override kept.** `CreatePromotionRecordRequest.feeClosureConfirmed`
is removed entirely, not deprecated-but-ignored — keeping a dead field
on the request DTO that silently does nothing would be worse than
removing it (a caller reading the DTO would reasonably assume it still
does something). `academic_closure_confirmed` never had a caller-side
field to begin with; this ADR brings `fee_closure_confirmed` to the
same shape rather than inventing a new admin-override concept BR-SIS-001
does not itself ask for. If a genuine manual-override need surfaces
later (e.g., a fee waived administratively outside Fees' own waiver
mechanism), `ScholarshipWaiverService` — which already exists for
exactly this kind of exception — is the right place to record it, not a
promotion-time boolean.

This follows the same call shape Examination already uses to reach into
another module: `PromotionService` calling
`AppServices::invoiceService()->hasOutstandingBalance()` is structurally
identical to Attendance's `AttendanceService::isExamEligibilityAtRisk()`
pattern and to `AcademicSessionService`/`ClassService` calls already in
this same method — a one-way Examination → Fees dependency; Fees gains
no new dependency on Examination.

## Consequences

- New migration `2026-08-07-210001_AddRouteIdToFeeStructuresTable.php`:
  additive nullable `fee_structures.route_id`, unique key extended to
  include it.
- `FeeStructureModel::findByClassSessionCategory()` and
  `FeeStructureService::listByClassSessionCategory()` both gain an
  optional `?int $routeId` parameter.
- `TransportAllocationService` gains `getActiveAllocationForStudent()`
  (read) and `changeRoute()` (write + Fees trigger) — the latter is a
  genuinely new capability; BR-TRN-005 had no route-change mutator
  before this ADR. New endpoint: `POST /transport/allocations/{id}/
  change-route`.
- `InvoiceService` gains `recalculateForRouteChange()` (BR-TRN-005,
  explicit-trigger only) and `hasOutstandingBalance()` (BR-SIS-001).
- `PromotionService::promoteStudent()` no longer accepts a caller-
  supplied fee flag; `CreatePromotionRecordRequest` and
  `PromotionController` drop the field entirely — this is a breaking
  API change to `POST /examination/promotions` (the request body no
  longer needs, and no longer honors, `fee_closure_confirmed`).
- Any future module that needs the same "route-scoped fee tier" idea
  (e.g., a hostel or club fee keyed by something other than
  class/session/category) should re-use the `route_id`-style additive
  column shape rather than inventing a new mechanism per module.
