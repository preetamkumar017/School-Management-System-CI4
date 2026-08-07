---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-010 record)
relates-to: Appendix-G ENT-SYS-005 (Configuration); Appendix-C v1.1 §3.5 (24 Consolidated Configurable Items); every prior ADR's "decided default, pending a future Configuration entity" note (ADR-005 §6/§7, ADR-006 §7/§8/§11, ADR-007 §4, ADR-008 §7, ADR-009 §2/§3/§4/§6)
---

# ADR-011: Configuration entity — which decided defaults actually migrate

## Context

Every Stage 6 module hit the same gap repeatedly: a business rule names a
threshold/rate/limit as "Client/Product Decision Required," no
`Configuration` entity existed yet to hold it, so each pass picked a
documented, tunable default and hardcoded it as a private class
constant on the relevant Service — ten of them now exist, across six
already-shipped modules. Appendix-C §3.5 consolidates the full set: **24
items** across 11 modules depend on a not-yet-confirmed configuration
value. `ENT-SYS-005` (`Configuration`, table `configurations`,
`setting_key`/`setting_value`/`data_type`/`module`/`is_editable`) is the
entity built to hold them, per its own Appendix-G card — a system-wide,
dot-notation-namespaced key-value store, owned by Administration
(`Module: SYS`), read by every other module at runtime.

Not all 24 items are eligible to migrate here. Cross-checking Appendix-C
§3.5 against what Stage 6 actually built:

- **10 items already exist as real, working decided-default constants**
  in already-shipped Service code — these are the ones this ADR moves
  into `Configuration` for real, closing the "pending a future
  Configuration entity" note each one's originating ADR left open.
- **14 items were never implemented as a scalar constant at all** —
  either the surrounding feature itself is out of scope (BR-LIB-006
  Reservation (**since resolved, 2026-08-07: ADR-017 built a real
  `Reservation` entity plus `ReservationService::notifyNextInQueue()`/
  `processExpiredNotifications()`, with only the notification response
  window (`library.reservation_response_window_hours`) migrated into
  `Configuration` — the FIFO ranking itself is a strict
  `requested_at`-order rule, not a scalar, matching the reasoning this
  ADR already gave for why a policy/list doesn't fit `Configuration`'s
  shape**), BR-TRN-003 GPS, BR-TRN-006 driver/vehicle (**since resolved,
  2026-08-07: ADR-019 built real `Driver`/`Trip` entities plus
  `TripService::startTrip()` gating trip-start on stored license
  validity — not a `Configuration` row; the trip-start check is a
  cross-entity validation, not a scalar rate/threshold, matching the
  reasoning this ADR already gave for why a policy/workflow doesn't fit
  `Configuration`'s shape**), BR-FEE-007 GST (**since resolved,
  2026-08-07: ADR-020 built a real `InvoiceLineItem` entity, computing
  GST per line from the already-stored `FeeHead.is_taxable`/`gst_rate` —
  not a `Configuration` row; the taxable-fee-head list and its rates are
  per-`FeeHead` data, not a single scalar, matching the reasoning this
  ADR already gave for why a policy/list doesn't fit `Configuration`'s
  shape**),
  BR-COM-002/003/005 — all named out-of-scope in ADR-006/007/009/010), or
  the "configurable item" is a policy/list/role-name rather than a
  single scalar value naturally suited to `Configuration`'s
  `setting_value VARCHAR(500)` shape (BR-ADM-007/008 seat-hold/waitlist
  policy — Admission's approved design never modeled a hold-timer at
  all (**since resolved, 2026-08-07: ADR-016 built a real
  `applications.hold_expires_at` column plus
  `ApplicationService::releaseExpiredHolds()`, with only the hold
  *duration* (`admission.seat_hold_period_hours`) migrated into
  `Configuration` — the ranking policy itself (BR-ADM-008) is a strict
  `submitted_at`-order rule, not a scalar, matching the reasoning this
  ADR already gave for why a policy/list doesn't fit `Configuration`'s
  shape**); BR-TT-004 subject-teacher eligibility source (**since resolved,
  2026-08-07: ADR-013 built this as a real `SubjectTeacherEligibility`
  table, not a `Configuration` row — a persisted mapping, not a scalar,
  matching the reasoning this ADR already gave for why a policy/list
  doesn't fit `Configuration`'s shape**); BR-EXM-007 board
  affiliation — already a per-`GradingScheme` column, not a global
  setting; BR-FEE-005 RTE waived fee-head list; BR-HR-002 exit SLA —
  ADR-008 §5 already resolved this by making deactivation synchronous,
  no SLA needed; BR-HR-004 override authority — a role name, and this
  codebase has never wired role-based authorization into a Controller,
  ADR-007 §8's standing precedent; BR-HR-005 PF/ESI/PT slabs — caller-
  supplied per ADR-008 §8, not a scalar rate; BR-FEE-008 overdue
  threshold — the current implementation only compares against
  `due_date`, no separate constant exists to migrate). Migrating these
  would mean inventing new enforcement logic under cover of "just adding
  config," which is a scope expansion this ADR doesn't make unilaterally.

One additional decided default is folded in even though it isn't named
individually in Appendix-C §3.5's 24-item table: HR & Payroll's leave
type annual allocations (CL 12 / SL 10 / EL 15, ADR-008 §7) — the table
names BR-HR-004's *override authority* as the configurable item, not the
allocation amounts themselves, but the allocations are the same category
of hardcoded decided default this pass is closing out, so they move too.

## Decision

### 1. `Configuration` — implemented per Appendix-G, in the Administration module

Maps directly onto its attribute catalogue: `setting_key` (unique,
dot-notation, e.g. `library.max_books_per_borrower`), `setting_value`
(`VARCHAR(500)`, stored as text, parsed per `data_type`), `data_type`
(`String`/`Number`/`Boolean`/`Date`), `module` (owning module name),
`is_editable`. Lives in `App\Modules\Administration` — `ENT-SYS-005` is
explicitly `Module: SYS`, joining `User`/`Role`/`AuditLog` as
Administration's own entities, not a new top-level module.

### 2. No public create endpoint — seeded at migration time, matching the entity's own Lifecycle line

Appendix-G's Lifecycle is explicit: "Created at implementation →
Modified by IT Admin as policy changes → versioned, not deleted." The
eleven keys this pass migrates (§4) are seeded directly in the creation
migration, not via a runtime `POST`. Only `PATCH` (IT Admin edits a
value) and `GET` are exposed over HTTP — matches the documented
lifecycle exactly and avoids a wide-open "create arbitrary config key"
endpoint no business rule actually asks for.

### 3. `ConfigurationService::getNumber()`/`getString()`/`getBoolean()` — typed reads, `is_editable` enforced on write

Every consuming Service reads through a typed accessor
(`getNumber(string $key): float`, matching `data_type = Number`) rather
than parsing `setting_value` itself in six different places. `update()`
rejects with `BusinessRuleException` (`CONFIGURATION_NOT_EDITABLE`) if
`is_editable = false` — no row is seeded that way in this pass, but the
column and the check are both real, per Appendix-G's own attribute.

### 4. Eleven keys, closing ten already-shipped modules' "decided default" notes

| Key | Value | Closes |
|---|---|---|
| `library.max_books_per_borrower` | 3 | ADR-009 §2 (BR-LIB-001) |
| `library.fine_per_day_rate` | 2.00 | ADR-009 §3 (BR-LIB-002) |
| `library.replacement_charge` | 500.00 | ADR-009 §4 (BR-LIB-003) |
| `library.outstanding_fine_threshold` | 0.00 | ADR-009 §6 (BR-LIB-005) |
| `timetable.weekly_load_ceiling` | 30 | ADR-006 §7 (BR-TT-006) |
| `attendance.exam_eligibility_min_percentage` | 75.0 | ADR-006 §11 (BR-ATT-006) |
| `attendance.edit_window_days` | 0 | ADR-006 §8 (BR-ATT-003) — reworked from a same-day boolean check to a numeric day-window comparison, the same semantic, now parametrized |
| `examination.anomaly_threshold_percentage_points` | 30.0 | ADR-005 §6 (BR-EXM-006) |
| `fees.late_fee_rate_percentage` | 5.0 | ADR-007 §4 (BR-FEE-004) |
| `hr_payroll.leave_allocation.cl` | 12 | ADR-008 §7 (BR-HR-004, allocation half) |
| `hr_payroll.leave_allocation.sl` | 10 | ADR-008 §7 |
| `hr_payroll.leave_allocation.el` | 15 | ADR-008 §7 |

(Twelve rows — `hr_payroll.leave_allocation.*` is three keys, not one,
since each leave type needs its own editable value.) Every consuming
Service (`BookIssueService`, `TimetableEntryService`, `AttendanceService`,
`MarksRecordService`, `InvoiceService`, `LeaveRequestService`) is updated
in this same pass to call `ConfigurationService` instead of its own
private constant — small, targeted changes to already-shipped code, the
same shape every prior stage has used when closing a previously-deferred
seam (ADR-006 §11, ADR-008 §2, ADR-009 §3).

### 5. The remaining fourteen Appendix-C §3.5 items are not migrated

Per Context — either the feature they'd configure is itself out of
scope in an already-accepted ADR, or the item is a policy/list/role-name
rather than a scalar `Configuration` can naturally hold. Listed
explicitly here so this ADR is the one place documenting *why* Stage 6's
"~15 candidates" note doesn't become "24 rows migrated" — most were
never real, working constants to begin with.

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s Administration row
  gains `Configuration` (`ENT-SYS-005`) as designed; `Document`/
  `ApprovalRequest` remain not yet designed (unchanged, still deferred
  per Stage 1).
- `docs/design/administration/Phase-Configuration-*` (this ADR's Phase
  docs) proceed on the basis of every decision above.
- `App\Modules\Library\Services\BookIssueService`,
  `App\Modules\Timetable\Services\TimetableEntryService`,
  `App\Modules\Attendance\Services\AttendanceService`,
  `App\Modules\Examination\Services\MarksRecordService`,
  `App\Modules\Fees\Services\InvoiceService`, and
  `App\Modules\HrPayroll\Services\LeaveRequestService` all change in
  this same pass to depend on `ConfigurationService` — six small,
  targeted changes to already-shipped code, gated by existing tests
  (same seeded values, so prior test assertions against the old hardcoded
  numbers still hold).
- A future pass revisiting any of the fourteen out-of-scope items (§5)
  should re-check this ADR first — most need a real feature built before
  a configuration value has anywhere to plug in, not just a
  `Configuration` row.
