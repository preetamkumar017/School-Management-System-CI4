---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-015 record)
relates-to: Appendix-C BR-ADM-007 (Provisional Seat Hold Expiry), BR-ADM-008 (Waitlist Ranking Order); Appendix-C §3.2 Observation A (named BR-ADM-001/BR-ADM-007 race condition); ADR-011 §5 (named these as the two Configuration candidates that don't fit `Configuration`'s scalar shape, "Admission's approved design never modeled a hold-timer at all"); ADR-004 (Admission↔SIS dependency direction, `SELECT ... FOR UPDATE` precedent); ADR-003 (`SeatAllocationModel::incrementSeatsFilled` row-lock discipline)
---

# ADR-016: Admission seat-hold expiry and waitlist ranking

## Context

Appendix-C names two Admission business rules that ADR-011 explicitly
declined to migrate into `Configuration`, for two different reasons that
both point to the same underlying gap:

- **BR-ADM-007** (Provisional Seat Hold Expiry): "A seat held pending fee
  payment is automatically released back to available inventory after
  the configured hold period elapses." ADR-011 §5 could not migrate this
  because no hold-timer existed anywhere in Admission's approved design
  to begin with — there was no scalar constant to move into
  `Configuration`, only a feature that was never built.
- **BR-ADM-008** (Waitlist Ranking Order): "Waitlisted applicants must be
  offered a vacated seat strictly in the order defined by the configured
  ranking/priority policy." ADR-011 §5 could not migrate this either,
  for the opposite reason — Appendix-C's own §3.5 table describes the
  configurable item as "Waitlist ranking/priority policy (sibling,
  staff-ward, etc.)," a policy/list, not a single scalar value that fits
  `Configuration`'s `setting_value VARCHAR(500)` shape.

Both rules also interact with a named, documented risk: Appendix-C §3.2
Observation A states that BR-ADM-007's automatic release and BR-ADM-001's
Seat Capacity Ceiling (which "assumes seat counts only ever decrease upon
confirmation") can race if a hold expires at the same moment a
confirmation attempt is in flight, and requires "a single atomic
transaction (hold-expiry-check + seat-decrement)," not two independently
timed operations. This ADR designs and implements both rules together,
closing that risk with the same row-lock discipline `SeatAllocationModel::
incrementSeatsFilled` already established (ADR-003/Phase-4).

No scheduler exists in this codebase (ADR-007 §4 precedent — BR-FEE-004/
008 are explicit-trigger Service methods, not cron jobs) and this ADR
does not introduce one.

## Decision

### 1. A hold is the existing `SHORTLISTED` status, not a new status value

`shortlistApplication()` (`VERIFIED -> SHORTLISTED`) already represents
"this applicant has been offered a seat" — FR-02's flow treats
`SHORTLISTED` as the pre-confirmation, pre-payment state a candidate sits
in before Confirm Enrollment. Rather than invent a new status value
(e.g. `HOLD_PENDING`) that duplicates what `SHORTLISTED` already means,
this ADR adds one additive nullable column, `applications.hold_expires_at`
(`DATETIME`, `NULL`), set to `now + <configured hold period>` at the
moment an application is shortlisted (first offer) or re-offered
(promotion off the waitlist, see §4). It is cleared (`NULL`) whenever the
application leaves the "actively holding a seat offer" state: on
`waitlistApplication()`, `rejectApplication()`, and
`confirmEnrollment()`.

This keeps the existing six-value status enum (`SUBMITTED`/`VERIFIED`/
`SHORTLISTED`/`WAITLISTED`/`ADMITTED`/`REJECTED`) completely unchanged —
no enum expansion, no restructuring of an approved schema, matching this
project's additive-only discipline (Ground Rules).

### 2. Hold duration: `admission.seat_hold_period_hours` = `72`, a new `Configuration` key

Per ADR-011's own precedent for how tunable defaults get read
(`ConfigurationService::getNumber()`, already wired into six Services),
`ApplicationService` reads this key rather than hardcoding it. Seeded via
a new additive migration inserting one row into `configurations`
(`module = 'Admission'`, `data_type = 'Number'`, `is_editable = true`) —
the original `CreateConfigurationsTable` migration already ran in every
environment, so a new key is added the same way `AddRouteIdToFeeStructures
Table` added a column: a later, additive migration, not an edit to
already-shipped migration history.

**72 hours (3 days)** is the concrete, documented default this ADR picks,
following ADR-007 §4/ADR-008 §7's established habit of choosing a real
number and stating the reasoning rather than leaving it symbolic: long
enough that a genuine applicant arranging a fee payment (often requiring
a bank visit or coordinating with a second parent) isn't rushed by a
same-day deadline, short enough that a seat isn't taken out of
circulation for more than a few days when the applicant has in fact
walked away — directly serving BR-ADM-007's stated justification
("protecting genuine waitlisted applicants" from indefinite blocking).
It is `is_editable = true`, so an IT Admin can tune it per-season without
a code change, exactly what `Configuration`'s Lifecycle line promises.

### 3. Ranking policy: `submitted_at` ascending — first-come-first-served, no priority categories invented

Appendix-C names "sibling, staff-ward, etc." only as *examples* of a
ranking/priority policy, not a mandated formula, and Appendix-G's
`Application` entity card carries no sibling/staff-ward/priority-category
field anywhere. Inventing one here would be a scope expansion this
project has repeatedly refused elsewhere (ADR-007 §7 declined to invent
an unapproved `Invoice` line-item structure for GST on the same
reasoning). With no priority-category data to rank on, the only
defensible, already-present ordering signal is submission order: the
earliest-submitted `WAITLISTED` application for a given `class_applied_id`
is promoted first.

This ADR ranks by `Application.submitted_at` ascending, not the generic
`created_at` audit column `BaseEntity` gives every entity. Both carry the
same value in practice (`createApplication()` sets `submitted_at =
Time::now()` in the same call the Model's `created_at` auto-stamp fires),
but `submitted_at` is Application's own business-meaningful timestamp —
already the column `ApplicationModel::findByStatus()` and
`paginateByFilters()` order by — and is the one exposed on
`ApplicationResponse`. Using it instead of the audit column is a more
idiomatic fit for this codebase's existing conventions, not a different
ranking outcome.

Ranking is also scoped to `class_applied_id` only, not
`class_applied_id + academic_session_id` — `SeatAllocation` (and
therefore a vacated seat) is already looked up by
`(class_id, academic_session_id)` against the current `ACTIVE` session
(`AcademicSessionService::getCurrentActiveSession()`, the same call
`confirmEnrollment()` already makes), so the waitlist candidate search is
naturally scoped to applications for that class regardless of session,
mirroring `confirmEnrollment()`'s own existing session resolution rather
than duplicating it on `Application` (which has no `academic_session_id`
column — applicants apply to a class, not a session).

### 4. `SeatAllocationService::releaseExpiredHolds()` — explicit-trigger, row-locked, atomic per application

A new public method, callable on demand (an Admin Staff action today; a
future scheduler once one exists, unchanged from this project's standing
position). For each `SHORTLISTED` application whose `hold_expires_at` has
passed:

1. Lock that `Application` row (`SELECT ... FOR UPDATE`, a new
   `ApplicationModel::lockForUpdate()` following exactly
   `SeatAllocationModel::incrementSeatsFilled()`'s existing raw-SQL
   row-lock shape) inside a transaction this method owns.
2. **Re-check eligibility under the lock**: still `SHORTLISTED`, still
   `hold_expires_at` in the past. If not — a concurrent `confirmEnrollment()`
   call already resolved it first — skip it; nothing to release.
3. If still eligible: transition it to `REJECTED` (`decided_at` set,
   `hold_expires_at` cleared). No new status is invented for "missed the
   payment window" — `REJECTED` already means "this application will not
   be admitted," which is exactly what a lapsed, unpaid seat offer is;
   this reuses an existing terminal state rather than adding a seventh
   enum value for a distinction Appendix-C's approved status list never
   named.
4. Look up the earliest-`submitted_at` `WAITLISTED` application for the
   same `class_applied_id` (§3), lock *that* row too, re-confirm it is
   still `WAITLISTED` under the lock (guards against two concurrent
   `releaseExpiredHolds()` calls double-promoting the same applicant),
   and if found, promote it: `status = SHORTLISTED`,
   `hold_expires_at = now + <configured hold period>` — the same
   "offered a seat, holding starts now" transition `shortlistApplication()`
   performs, just triggered by promotion instead of a staff action.
5. Commit. Every write goes through `AuditService::record()` (the
   `REJECTED` transition and, when it happens, the promotion), matching
   the Ground Rules.

This closes Observation A's named race directly: `confirmEnrollment()`
is extended (§5) to lock the same `Application` row before acting, so a
`releaseExpiredHolds()` pass and a `confirmEnrollment()` call for the
same application can never both succeed — whichever transaction acquires
the row lock first completes its full check-and-update atomically before
the other proceeds, and the loser sees the post-lock, authoritative
status (no longer `SHORTLISTED`) rather than a stale pre-lock read.
`SeatAllocation.seats_filled` itself is untouched by holding or
releasing — it is only ever incremented at `confirmEnrollment()`,
unchanged from ADR-004 §5 — so this ADR does not touch, and does not
need to re-lock, the `seat_allocations` row at all; the race named in
Observation A is between two operations on the *same `Application` row*
(a hold expiring vs. that same application being confirmed), not between
two operations on `SeatAllocation`.

### 5. `confirmEnrollment()` gains a lock-and-recheck step

Immediately after `$db->transStart()`, before the existing
`incrementSeatsFilled()` call, `confirmEnrollment()` now calls
`ApplicationModel::lockForUpdate($id)` and re-verifies the locked row's
status is still `SHORTLISTED` or `WAITLISTED` (`APPLICATION_INVALID_
STATUS_TRANSITION` otherwise) — the same row `releaseExpiredHolds()`
locks in §4. On success it also clears `hold_expires_at`. This is the
minimal change that gives Observation A's "single atomic transaction"
requirement teeth: the pre-existing plain `find()` read at the top of
`confirmEnrollment()` (used for the seat-allocation/session/duplicate-
identity checks) is left as-is for those pre-checks, but the actual
status transition is now guarded by the same lock `releaseExpiredHolds()`
uses, not a second, independently-timed operation.

### 6. No new public endpoint for placing a hold; one new endpoint for releasing them

Holding starts automatically as a side effect of the existing
`POST /admission/applications/{id}/shortlist` — no new endpoint needed.
Releasing expired holds is a new explicit-trigger action:
`POST /admission/applications/release-expired-holds`, returning a summary
of what was released and, where applicable, who was promoted — callable
by Admin Staff today, and the seam a future scheduler would call once
one exists.

## Consequences

- New migration `2026-08-07-220001_AddHoldExpiresAtToApplicationsTable.php`:
  additive nullable `applications.hold_expires_at DATETIME NULL`.
- New migration `2026-08-07-220002_AddAdmissionSeatHoldPeriodConfigKey.php`:
  seeds `admission.seat_hold_period_hours = '72'` into `configurations`.
- `Application` entity gains `hold_expires_at` (nullable date cast).
- `ApplicationModel` gains `lockForUpdate()` (raw `SELECT ... FOR UPDATE`)
  and `findEarliestWaitlistedForClass()`; `hold_expires_at` added to
  `allowedFields`.
- `ApplicationService` gains `releaseExpiredHolds()`; `shortlistApplication()`,
  `waitlistApplication()`, `rejectApplication()`, and `confirmEnrollment()`
  are extended as described in §1/§4/§5. `ApplicationService` now depends
  on `ConfigurationService` (constructor injection, same pattern
  `LeaveRequestService`/`InvoiceService` already use).
- New endpoint: `POST /admission/applications/release-expired-holds` on
  `ApplicationController`, with the established `#[OA\Post]` attribute.
- `ApplicationResponse` gains `hold_expires_at`.
- This ADR does not touch `SeatAllocation.seats_filled` accounting,
  `SeatAllocationModel::incrementSeatsFilled()`, or the Admission↔SIS
  dependency direction established by ADR-003/ADR-004 — Student stub
  creation is unaffected; a hold is resolved entirely within Admission's
  own `Application`/`SeatAllocation` entities before `confirmEnrollment()`
  ever reaches SIS.
- A future stakeholder decision to add real sibling/staff-ward priority
  categories to `Application` would change §3's ranking key but not this
  ADR's row-lock/transaction design in §4/§5 — that part is independent
  of which field the ranking query orders by.
