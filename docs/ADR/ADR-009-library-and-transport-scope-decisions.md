---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004/005/006/007/008 record)
relates-to: Appendix-C v1.1 (BR-LIB-001–006, BR-TRN-001–006); Appendix-E (FR-27–32); ADR-007 §3 (BR-FEE-003 Transport auto-linkage, still open)
---

# ADR-009: Library and Transport module scope — two small, dependency-clean modules, bundled in one pass

## Context

Per the roadmap, Stage 6e's four remaining modules (Library, Transport,
Communication, Reports) have no hard ordering between them. Library and
Transport are bundled here because both are small (2 and 3 entities),
both checked out clean against Appendix-G's FK graph (the Stage 6b
lesson — check before assuming), and neither depends on the other or on
Communication/Reports. `BookIssue.borrower_ref_id` is polymorphic against
`Student` (SIS) or `Employee` (HR & Payroll) — both now exist, so unlike
every prior undesigned-dependency stub this project has needed, this pass
needs none. `TransportAllocation.student_id → Student` is likewise
already satisfiable.

Two structural gaps surfaced during the design pass, both resolved by a
decided additive column rather than an invented entity, following the
established pattern (ADR-005 §10, ADR-008 §4):

1. `Book`'s attribute catalogue has no status field beyond `is_available`
   (boolean), yet its own Lifecycle line names `Lost/Damaged` as a
   distinct state, and BR-LIB-003 needs to track a replacement charge
   per loss event. `is_available` alone can't carry that.
2. `Route`'s and `Vehicle`'s Relationships/Child-Entities sections
   describe a Route↔Vehicle link ("One-to-Many with Route" from
   Vehicle's side), but neither entity's attribute catalogue lists an FK
   column for it — BR-TRN-001 ("Route.capacity ≤ assigned Vehicle
   capacity") is unimplementable without one.

Several BR-LIB/BR-TRN items reference "Client/Product Decision Required"
configuration values or cross-module postings (library fines/replacement
charges into the fee ledger, route-based fee-tier recalculation) that
mirror gaps ADR-007 already named for Fees — Fees' current design has no
generic "post an ad-hoc charge" capability, only session/class-scoped
`FeeStructure` invoicing, so those postings remain deferred rather than
invented against a Fees API that doesn't exist.

## Decision

### 1. `Book` and `BookIssue` — implemented per Appendix-G, with one decided additive column

`Book` maps directly onto its attribute catalogue. `BookIssue.
borrower_ref_id` is validated against SIS's `StudentService` or HR &
Payroll's `EmployeeService`, dispatched on `borrower_type` — the first
polymorphic-FK validation this codebase has done against two live
modules simultaneously, both now available. A decided additive column,
`BookIssue.status` (`Issued`/`Returned`/`Lost`, not in Appendix-G's
literal attribute list), tracks the loss/return state item 1 above
identifies as missing — the same kind of decided addition as Academic's
`locked_by_closed_exam` (ADR-005 §10) and Fees' `late_fee_applied`
(ADR-007). `Book.is_available` is derived from whether the book has any
`Issued` `BookIssue` row, not stored redundantly by the caller.

### 2. BR-LIB-001 (Max Books Per Student) — implemented with a decided default

"Client/Product Decision Required" per Appendix-C — decided here as **3
books per borrower**, a documented, tunable default, pending a future
`Configuration` entity (joining the list ADR-005/006/008 already keep).
Counts the borrower's currently `Issued` `BookIssue` rows.

### 3. BR-LIB-002 (Overdue Fine Calculation) — implemented, not posted to Fees

Per-day rate decided as **₹2/day**, a documented default (same
treatment as item 2). `BookIssueService::returnBook` computes
`fine_amount = max(0, days_overdue) × rate` and stores it on the
`BookIssue` row itself — Appendix-G's own `fine_amount` column. Posting
to the fee ledger (FR-28's stated post-condition) is **not implemented**:
Fees' current design (ADR-007) has no ad-hoc-charge endpoint, only
`FeeStructure`-driven invoicing per class/session — inventing one here
would expand Fees' own approved scope from a different module's pass.
Deferred, matching ADR-007 §3's symmetric Transport-side deferral.

### 4. BR-LIB-003 (Lost Book Replacement Charge) — implemented with a decided flat fee, not posted to Fees

No `price` field exists anywhere on `Book`'s attribute catalogue, so
"fixed fee vs. current book price" (Appendix-C's own stated ambiguity)
resolves itself — only a fixed fee is possible against the approved
schema. Decided as **₹500 flat**, a documented default. `BookIssueService::
reportLost` sets `status = Lost` (item 1's additive column), computes the
charge, and stores it on the `BookIssue` row via a second decided
additive column, `replacement_charge_amount` — same reasoning as item 1.
Not posted to Fees, same reasoning as item 3.

### 5. BR-LIB-004 (Reference Book Non-Circulation) — implemented for real

Fully self-contained against `Book.classification`. `BookIssueService::
issueBook` rejects with `BOOK_NOT_CIRCULATING` if the book's
classification is `Reference`.

### 6. BR-LIB-005 (Issue Block on Outstanding Fine) — implemented with a decided threshold

Threshold decided as **₹0** — any nonzero unpaid fine (sum of
`fine_amount`/`replacement_charge_amount` across the borrower's
`BookIssue` rows not marked settled) blocks a new issue, a documented,
tunable default. Since fines aren't posted to Fees (items 3, 4), "unpaid"
is tracked entirely within `BookIssue` via a decided additive boolean,
`fine_settled` — a Librarian marks it settled directly (no payment
gateway integration exists at this layer either).

### 7. BR-LIB-006 (Reservation Queue Priority) is out of scope

No `Reservation` entity exists anywhere in Appendix-G's Library
catalogue — FIFO queue semantics need one, and it isn't invented here,
matching every prior "no approved entity, don't build one" precedent
(e.g., ADR-006 §3 Substitution, ADR-008 §9 Settlement).

### 8. `Route`/`Vehicle` link — a decided additive FK column, not an invented entity

`Route` gains a decided additive column, `vehicle_id` (nullable BIGINT,
intra-module FK to `vehicles`) — not in Appendix-G's literal attribute
list, but required by the Relationships section's own stated Vehicle↔
Route link and by BR-TRN-001's capacity-ceiling comparison, the same
category of gap-closing addition as items 1/2 above, just an FK instead
of a flag/enum.

### 9. BR-TRN-001 (Vehicle Capacity Ceiling) — implemented for real, with a real row lock

Two checks, both enforced: `RouteService::createRoute`/`updateRoute`
rejects if `capacity` exceeds the assigned `Vehicle.capacity` (when
`vehicle_id` is set — optional, since a Route can be configured before a
Vehicle is assigned). `TransportAllocationService::allocate` locks the
target `Route` row (`SELECT ... FOR UPDATE`, the same concurrency-safe
shape `SeatAllocationModel::incrementSeatsFilled` established in Stage 4
for Admission) before counting active allocations and comparing against
`Route.capacity`, inside one transaction with the insert — a genuine
database-level guard against the ceiling being breached under concurrent
requests, not a check-then-insert race.

### 10. BR-TRN-002 (Single Route Per Student) — implemented for real, inside the same lock

`TransportAllocationService::allocate` checks for an existing `Active`
allocation for the student inside the same locked transaction as item 9
— no DB-level partial-unique index is added (MySQL has no native partial
unique index; a generated-column workaround was considered and rejected
as unnecessary complexity given the Service-layer check is already
inside a real transaction with a row lock).

### 11. BR-TRN-003 (GPS Update Interval Compliance) is out of scope

Needs a live GPS device data-ingestion pipeline and a vendor integration
explicitly marked "Client/Product Decision Required" per Appendix-C — no
such pipeline exists anywhere in this codebase. `Vehicle.gps_device_id`
is stored as a plain identifier column, per Appendix-G, with no live-feed
behavior attached — same reasoning as BR-ATT-007's biometric-consistency
deferral (ADR-006 §10).

### 12. BR-TRN-004 (Mandatory Emergency Contact) — implemented for real, self-contained

`TransportAllocation.emergency_contact` (Appendix-G's own attribute, not
a Student-profile field, despite Appendix-C's prose referencing "student
profile") is a required, validated (10-digit numeric) field on
`TransportAllocationService::allocate` — fully self-contained, no
cross-module dependency, matching the attribute catalogue exactly rather
than the business-rule prose's looser description.

### 13. BR-TRN-005 (Route Change Fee Recalculation) is out of scope

Needs a route-based fee-tier configuration that doesn't exist in Fees'
approved `FeeStructure` design (keyed by class/session/category, not
route) — this is the same seam ADR-007 §3 already named from Fees' side
(BR-FEE-003 Transport auto-linkage) and remains open from both
directions. Not implemented here either; a future joint Fees/Transport
pass is the right place to close it, not a unilateral addition to either
module's already-approved scope.

### 14. BR-TRN-006 (Driver/Vehicle Assignment Validity) is out of scope

Needs `Driver` and `Trip` entities, neither of which exists anywhere in
Appendix-G's Transport catalogue (only `Route`/`Vehicle`/
`TransportAllocation`). `Vehicle.license_valid_until` is stored, per
Appendix-G, but no trip-start workflow exists to gate against it. Not
implemented — a future design pass scoping `Driver`/`Trip` entities is
the prerequisite, not something to invent here.

**Since resolved, 2026-08-07**: ADR-019 built real `Driver`/`Trip`
entities plus `TripService::startTrip()`, gating trip-start on the
Route's own assigned driver's/vehicle's stored license validity
(`license_valid_until >= today`), with six distinct error codes
identifying the specific missing/expired credential. The external
licensing-data-source integration named in BR-TRN-006's own
Precondition remains explicitly out of scope (ADR-019 §4) — self-
reported/stored data only, same restraint this ADR already applied to
GPS live-tracking (§11).

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s Library and
  Transport rows are updated to Designed, citing this ADR.
- `docs/design/library/` and `docs/design/transport/` (this ADR's Phase
  docs) proceed on the basis of every decision above; none are
  re-derived there.
- A future `Configuration` module design must account for: max-books
  limit (§2), overdue fine rate (§3), replacement charge (§4), and
  outstanding-fine threshold (§6) — four more candidates, joining the
  list ADR-005/006/008 already keep.
- A future Fees module follow-up must account for: library
  fines/replacement charges needing a generic ad-hoc-charge posting
  capability (§3, §4) and route-based fee-tier recalculation (§13,
  reopening ADR-007 §3 from the Fees side too).
- A future Library follow-up must account for: `Reservation` (§7).
- A future Transport follow-up must account for: GPS live-tracking
  ingestion (§11), and `Driver`/`Trip` entities for trip-start validation
  (§14).
