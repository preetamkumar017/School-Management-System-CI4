---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-016 record)
relates-to: Appendix-C BR-LIB-006 (Reservation Queue Priority), FR-27/FR-28; ADR-009 §7 (named BR-LIB-006 out of scope — "No Reservation entity exists anywhere in Appendix-G's Library catalogue"); ADR-011 §5 (listed BR-LIB-006 among the fourteen items that never became a scalar constant, so had nothing to migrate into Configuration); ADR-016 (same shape of problem — FIFO queue + a Client/Product-Decision-Required response window + no-scheduler explicit-trigger mechanism + SELECT ... FOR UPDATE row-lock discipline)
---

# ADR-017: Library reservation queue (BR-LIB-006)

## Context

Appendix-C's BR-LIB-006 says a returned/available book must be offered
to the longest-waiting reservation holder before general issue, FIFO by
request timestamp, and that if the notified holder doesn't respond
within "the configured window (Client/Product Decision Required)," the
next holder in queue is offered instead. ADR-009 §7 declined to build
this because no `Reservation` entity exists anywhere in Appendix-G's
Library catalogue — the same "no approved entity, don't invent one"
posture this project held everywhere until it hit a Business Rule whose
own Trigger/Precondition/Post-condition fields described concrete,
structured behavior a scalar `Configuration` row can't hold. ADR-013
(`Substitution`/`SubjectTeacherEligibility` for BR-TT-004) and ADR-016
(`Application.hold_expires_at` for BR-ADM-007/008) both crossed that
line for exactly that reason. BR-LIB-006 is the same shape: FIFO queue,
membership, notify state, and a response-window timer are all
structured data no `setting_value VARCHAR(500)` row can represent. This
ADR is the "future Library follow-up" ADR-009's own Consequences
section already named as pending.

No scheduler exists in this codebase (ADR-007 §4, ADR-016 §4 precedent)
and this ADR does not introduce one.

## Decision

### 1. A new `Reservation` entity, reusing `BookIssue`'s polymorphic-borrower shape exactly

`reservations` (`reservation_id`, `book_id`, `borrower_type`
[`Student`/`Employee`], `borrower_ref_id`, `requested_at`, `status`,
`notified_at`, `notification_expires_at`, plus the standard audit/
soft-delete columns). `borrower_type`/`borrower_ref_id` is the identical
polymorphic shape `BookIssue` already uses and ADR-009 §1 already
validated against SIS's `Student` or HR & Payroll's `Employee` — the
same concept ("who is on the other end of a library transaction"), so
this reuses it rather than inventing a second shape for the same idea.
`book_id` is a real intra-module FK to `books`, same as `BookIssue.
book_id`.

### 2. `status` enum: `Waiting` / `Notified` / `Fulfilled` / `Expired` / `Cancelled`

- `Waiting` — queued, book not yet offered to this holder.
- `Notified` — the book is available and this is the longest-waiting
  holder; the response-window clock (`notification_expires_at`) is
  running.
- `Fulfilled` — the notified holder issued the book before the window
  lapsed; the reservation's purpose is served (terminal).
- `Expired` — the notification window lapsed without the holder
  collecting the book; the next holder was offered instead (terminal).
- `Cancelled` — the holder or a Librarian withdrew the reservation
  before it was fulfilled, whether still `Waiting` or already `Notified`
  (terminal).

Five states, not fewer: `Waiting` and `Notified` must be distinct
because only one reservation per book can legitimately be blocking
general issue at a time (BR-LIB-006's post-condition — "notified and
given priority"), and `Fulfilled`/`Expired`/`Cancelled` must be distinct
terminal states because each is a different, independently useful fact
for a Librarian looking at reservation history (did the patron collect
it, miss the window, or change their mind).

### 3. FIFO by `requested_at` ascending — no priority-category field invented

Same reasoning ADR-016 §3 gave for BR-ADM-008: Appendix-C's own
`BR-LIB-006` prose names only "FIFO by request timestamp," and
Appendix-G's Library catalogue has no priority/category attribute for
`Reservation` to rank on (it has no `Reservation` card at all). The only
defensible, already-present ordering signal is when the reservation was
requested. `Reservation.requested_at` (set at creation, `Time::now()`)
is the ranking key, scoped to `book_id` — a title-level (not general)
queue, since Library's `Book` entity is one row per physical copy
(`Book.is_available` is a single boolean, not a copy count), matching
how `BookIssue` already scopes availability to one `book_id` at a time.

### 4. Response window: `library.reservation_response_window_hours` = `48`, a new `Configuration` key

Following ADR-016 §2's exact precedent for how a decided default gets
made real and tunable (`ConfigurationService::getNumber()`, seeded by a
new additive migration into `configurations`, `module = 'Library'`,
`data_type = 'Number'`, `is_editable = true`).

**48 hours (2 days)** is the concrete, documented default — deliberately
shorter than Admission's 72-hour seat hold (ADR-016 §2), because the two
actions being waited on are not comparable in friction: confirming an
admission offer requires arranging a fee payment (often a bank visit,
sometimes coordinating a second parent), while collecting a reserved
book requires a student or staff member already on campus to walk into
the library, an action available to them essentially any day school is
in session. Two days is enough to notice a notification and make one
library visit without being unreasonably tight, while keeping a
high-demand title (the reason a queue exists at all — BR-LIB-006's own
justification is "fairness for students who reserved a high-demand
title") from sitting reserved-but-uncollected for the better part of a
week when the notified holder has in fact lost interest. `is_editable =
true` so a Librarian/IT Admin can tune it without a code change.

### 5. Two explicit-trigger Service methods, no scheduler

- **`ReservationService::notifyNextInQueue(int $bookId): ?ReservationResponse`**
  — the "book returned" trigger. Called automatically as the last step
  of `BookIssueService::returnBook()`, immediately after the book is
  marked available (mirrors `TimetableEntryService::reviseEntry`'s
  call-out to `NotificationLogService::create()` — a plain in-process
  method call, not an event bus). Finds the earliest-`requested_at`
  `Waiting` reservation for that `book_id`; if one exists, transitions
  it to `Notified`, stamps `notified_at = now`, and
  `notification_expires_at = now + <configured window>`, and logs a
  notification via `NotificationLogService::create()` (§7). Returns
  `null` if the queue for that book is empty — general issue proceeds
  unblocked, per BR-LIB-006's precondition ("a non-empty reservation
  queue exists").

  The Trigger field's second clause — "newly cataloged as available" —
  is a non-event in this codebase's actual `Book` design: `Book.
  is_available` is derived from whether an active `BookIssue` exists
  (ADR-009 §1), never a caller-supplied field (`UpdateBookRequest` has
  no `is_available` property), and a reservation can only exist against
  a `book_id` that was already catalogued (you cannot reserve a book
  that doesn't exist yet). There is no code path where a book becomes
  newly available except a return, so only the return trigger needs
  wiring; this is a scope-tightening observation, not an implemented
  no-op.

- **`ReservationService::processExpiredNotifications(): ProcessExpiredNotificationsResult`**
  — the window-expiry trigger, callable on demand (a Librarian action
  today; the seam a future scheduler would call, unchanged standing
  position). For every `Notified` reservation whose
  `notification_expires_at` has passed: transition it to `Expired`, then
  call `notifyNextInQueue()` for the same `book_id` to offer the next
  `Waiting` holder, if any. New endpoint:
  `POST /library/reservations/process-expired-notifications`, returning
  a summary of what expired and who was promoted — the same response
  shape `POST /admission/applications/release-expired-holds` established
  in ADR-016 §6.

### 6. Concurrency: `SELECT ... FOR UPDATE` scoped by `(book_id, status = 'Notified')`

The genuine race here is symmetric to Appendix-C §3.2 Observation A:
the notified holder issuing the book (`BookIssueService::issueBook`)
racing against `processExpiredNotifications()` expiring that same
notification at the same moment — one must win, and the loser must see
the authoritative post-lock state, not act on a stale read. Both paths
now lock with the identical predicate, `ReservationModel::
lockNotifiedForBook(int $bookId)` (raw `SELECT reservation_id, ...
FROM reservations WHERE book_id = ? AND status = 'Notified' FOR
UPDATE`), inside a transaction each method owns — the same row-lock
shape `SeatAllocationModel::incrementSeatsFilled`/`RouteModel::
findForUpdate`/`ApplicationModel::lockForUpdate` already established.
Because the predicate includes `status = 'Notified'`, a query that finds
no matching row (already `Expired` or already `Fulfilled` by the other
transaction) locks nothing and simply proceeds against that fresh state
— no separate "lock, then re-check" round trip is needed beyond what
the predicate itself already encodes, a slightly simpler shape than
ADR-016's `lockForUpdate` + manual re-check because the status is part
of the lock's own `WHERE` clause here rather than checked after an
unconditional row lock.

`BookIssueService::issueBook()` is extended: inside a transaction it
now owns (it previously had none), it locks any `Notified` reservation
for the target `book_id`. If one exists and belongs to a different
borrower, the issue is rejected (`BOOK_RESERVED_FOR_ANOTHER_BORROWER`)
— this is what makes BR-LIB-006's post-condition ("given priority to
collect/issue the book") a real enforced block on general issue, not
just a notification with no teeth. If it belongs to the requesting
borrower, the issue proceeds and the reservation is marked `Fulfilled`
in the same transaction. If none exists, issuance proceeds exactly as
before this ADR.

### 7. Notifications are log-only, reusing `NotificationLogService::create()` exactly

Per ADR-010's decided scope (no live SMS/Email/Push dispatch exists),
`notifyNextInQueue()` calls `NotificationLogService::create()` with
`channel = SMS` (Library's own default, same placeholder-channel
reasoning already used elsewhere), `trigger_event = 'BR-LIB-006 book
available for reservation'`, `recipient_type/recipient_ref_id` copied
from the reservation's `borrower_type`/`borrower_ref_id`.

This surfaces a real, small gap: `NotificationLog.recipient_type`'s
enum today is `Guardian`/`Employee`/`User` (ADR-010's approved shape) —
no `Student` value, because no prior notification-log call site needed
to address a Student directly. A reservation holder can be a `Student`
(the more common case for library patrons). This ADR adds `Student` as
a fourth `NotificationLog.recipient_type` value — an additive `ALTER
TABLE ... MODIFY COLUMN` migration widening the existing ENUM (no data
loss, every existing row's value is still valid), plus
`NotificationLog::RECIPIENT_STUDENT` and a `Student => StudentService::
getStudent()` branch in `NotificationLogService::validateRecipient()`.
This is Communication module surface, touched from a Library-owned ADR,
the same cross-module small-extension shape ADR-014 already used when
Fees needed a Transport-owned field.

## Consequences

- New migration `2026-08-07-230001_CreateReservationsTable.php`:
  `reservations` table as described in §1/§2.
- New migration `2026-08-07-230002_AddLibraryReservationResponseWindow
  ConfigKey.php`: seeds `library.reservation_response_window_hours =
  '48'` into `configurations`.
- New migration `2026-08-07-230003_AddStudentToNotificationLogsRecipient
  Type.php`: widens `notification_logs.recipient_type` ENUM to add
  `Student`.
- `Reservation` entity, `ReservationModel` (including
  `lockNotifiedForBook()`), `ReservationService`
  (`createReservation`/`cancelReservation`/`getReservation`/
  `listByBook`/`listByBorrower`/`notifyNextInQueue`/
  `processExpiredNotifications`), `ReservationController`,
  `CreateReservationRequest`/`ReservationResponse` DTOs — new Library
  module surface, following `BookController`/`BookIssueController`'s
  established conventions exactly.
- `BookIssueService::returnBook()` gains a call-out to
  `ReservationService::notifyNextInQueue()`. `BookIssueService::
  issueBook()` gains the reservation-priority block/fulfil check in §6,
  now wrapped in a transaction it previously didn't need.
- `NotificationLog::RECIPIENT_STUDENT` added; `NotificationLogService::
  validateRecipient()` extended; OpenAPI schemas for
  `NotificationLogCreateRequest`/`NotificationLogResponse` widened to
  include `Student`.
- New endpoints: `POST /library/reservations`,
  `POST /library/reservations/{id}/cancel`,
  `GET /library/reservations/{id}`, `GET /library/reservations` (by
  `book_id` or `borrower_type`+`borrower_ref_id`),
  `POST /library/reservations/process-expired-notifications`.
- `docs/development/School-ERP-Development-Roadmap.md`'s "Immediate next
  action" section is updated: BR-LIB-006 removed from the remaining
  Appendix-C items list, a new Stage 13 entry added.
- `docs/ADR/ADR-011-configuration-entity-scope-decisions.md`'s BR-LIB-006
  mention is corrected to point here, matching the precedent already set
  there for BR-TT-004 (ADR-013) and BR-ADM-007/008 (ADR-016).
- ADR-009 §7 itself is left as the historical record of the original
  scope decision, unedited — matching this project's standing practice
  (ADR-006's BR-TT-004 mention was likewise never retroactively edited;
  only ADR-011's consolidated tracking note gets the inline correction).
