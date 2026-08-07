---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-020 record; user explicitly chose MSG91 as the vendor and explicitly required a pluggable/dynamic interface design, not a hardcoded single-vendor integration)
relates-to: ADR-010 §1/§2/§5 (the original "no gateway vendor chosen" deferral, resolved here); Appendix-C BR-COM-002/003 (still out of scope, unblocked in principle but not implemented — no bulk-send/emergency-alert workflow exists yet); Appendix-E FR-37; Appendix-G Data Dictionary (Guardian is the only entity with mobile_number/email)
---

# ADR-021: Communication SMS/Email notification gateway (MSG91), pluggable by design

## Context

ADR-010 §1/§2/§5 left `NotificationLogService` as a log/record-keeping
Service only — `create`, `markDispatched`, `markDelivered`, `markFailed`
existed, but nothing ever called a real SMS/Email/Push vendor, because
no vendor was chosen and no HTTP client integration existed anywhere in
this codebase. The user has now explicitly chosen MSG91
(https://msg91.com, an Indian SMS/Email/WhatsApp API provider) as the
first vendor, and explicitly required a pluggable/dynamic design — a
future second vendor must be a new class, not a rewrite of
`NotificationLogService` or its callers.

A second, harder constraint governs the whole design: per Appendix-G's
Data Dictionary, only `Guardian` (SIS) has `mobile_number`/`email`
fields. `Employee`, `User`, and `Student` have no phone/email fields
anywhere in the approved schema (re-verified via
`pdftotext docs/Appendices/Appendix-G_Data_Dictionary_v1.0.pdf -` and
grepping every entity's attribute catalogue). Real dispatch is
therefore only genuinely possible for some `NotificationLog.recipient_type`
values, not all four the ENUM allows.

## Decision

### a. One `NotificationLogService`, two narrow interfaces — not a single channel-aware `send()`

`App\Modules\Communication\Gateways\SmsGatewayInterface::send(string $mobileNumber, string $message): void`
and `App\Modules\Communication\Gateways\EmailGatewayInterface::sendEmail(string $emailAddress, string $subject, string $message): void`.
Two interfaces rather than one `NotificationGatewayInterface` with a
channel-aware `send()`, because SMS and Email have genuinely different
call shapes (a mobile number vs. an address+subject) — a single
interface would need an internal `if ($channel === ...)` branch inside
every implementation, defeating the point of an interface boundary.
Both interfaces live under `App\Modules\Communication\Gateways` (module-
level, not `App\Core`) — this is Communication-module infrastructure,
the same place `NotificationLogService` itself lives; nothing else in
the codebase needs it.

`GatewayException` (`App\Modules\Communication\Gateways\GatewayException`,
extends `RuntimeException`) is the single exception type both
interfaces are documented to throw on any failure (non-2xx response,
network failure). `NotificationLogService::dispatch()` catches
`Throwable` broadly around the gateway call (a driver could throw
something else unexpectedly) and always converts it into a `Failed`
status with the exception message as `failure_reason` — never an
unhandled exception up to the Controller.

### b. `Msg91Gateway` — one class implementing both interfaces

`App\Modules\Communication\Gateways\Msg91\Msg91Gateway` implements both
`SmsGatewayInterface` and `EmailGatewayInterface` in a single class,
not two separate `Msg91SmsGateway`/`Msg91EmailGateway` classes. Both
channels share the same MSG91 account (`authkey`) and the same HTTP
client construction — splitting them into two classes would only
duplicate the `Config\Services::curlrequest()` wiring and the request/
response-handling method (`request()`) for no benefit. A future second
vendor that only supports one channel implements only that one
interface in its own class; nothing here assumes every driver supports
both.

Two real MSG91 endpoints are called:
- SMS (flow-based v5): `POST https://control.msg91.com/api/v5/flow/`
- Email (transactional v5): `POST https://api.msg91.com/api/v5/email/send`

Payload construction is isolated in one private method per channel
(`buildSmsPayload`, `buildEmailPayload`) — MSG91's exact field names
were not verified against a live account in this pass (no real
credentials available to this design/build process); if any field name
turns out to be wrong, fixing it is a one-method change, not a
redesign. The HTTP call itself uses `\Config\Services::curlrequest()`
(already available in this codebase, no new HTTP client dependency
added to `composer.json`).

### c. `Config\Notification` — MSG91 secrets from `.env`, mirroring `Config\Auth` exactly

`App\Config\Notification` reads `msg91AuthKey`, `msg91SenderId`,
`msg91SmsFlowUrl`, `msg91EmailUrl`, `msg91FromEmail`,
`msg91FromEmailDomain`, all via `env('notification.msg91.*', <default>)`
— the identical `env()`-backed pattern `Config\Auth::$jwtSecret` already
established. The real `authkey` is never hardcoded anywhere in the
codebase; it must be set in `.env` (unset defaults to an empty string,
which will fail every real MSG91 call cleanly with a 401/`GatewayException`
rather than silently succeed).

### d. `message_body` — an additive nullable column, caller-supplied at `create()` time

`NotificationLog` has no message-body field in Appendix-G's approved
schema. A real dispatch needs real content, so `notification_logs`
gains `message_body TEXT NULL` (migration
`2026-08-07-260001_AddMessageBodyToNotificationLogsTable.php`) —
additive, matching the precedent set by `locked_by_closed_exam`,
`hold_expires_at`, `route_id`, and `failure_reason` itself (ADR-010 §2).
`CreateNotificationLogRequest` gains `?string $messageBody = null`,
supplied by the caller at `create()` time — the same caller-supplied-
at-create shape `override_reason` already uses elsewhere in this
codebase. Every existing call site
(`AttendanceService::notifyGuardianOfAbsence`,
`TimetableEntryService::reviseEntry`, `SubstitutionService::createSubstitution`,
`ReservationService::notifyNextInQueue`) now passes a real, specific
message string for its trigger event instead of leaving the column
null. If `dispatch()` is called on an older row created before this ADR
(`message_body` still null), it falls back to sending `trigger_event`
as the message body rather than failing — a null message must never
block dispatch outright.

### e. The Guardian/Student/Employee-User resolution matrix — a real, named limitation, not silently dropped

`NotificationLogService::dispatch()` resolves contact info per
`recipient_type`:

- **Guardian** → `Guardian.mobile_number`/`Guardian.email` directly
  (`GuardianModel::find`).
- **Student** → a Student has no own contact field. Resolved via the
  student's primary-contact Guardian:
  `StudentGuardianLinkModel::findByStudentId()`, preferring the link
  with `is_primary_contact = true`, falling back to the first linked
  guardian if none is flagged primary — the identical fallback shape
  `AttendanceService::notifyGuardianOfAbsence` already used for its own
  guardian lookup before this ADR. This is a sensible, defensible
  default for a school ERP: a student's parent/guardian is who actually
  receives an SMS/Email about their child. If the student has no linked
  guardian at all, `dispatch()` fails with `NO_GUARDIAN_LINKED`.
- **Employee / User** → genuinely no phone/email field exists anywhere
  in Appendix-G's approved schema for either entity. `dispatch()`
  throws internally and is caught, marking the log `Failed` with:
  `"No contact information available for recipient_type {Employee|User}
  — Appendix-G does not model a phone/email field for this entity."`
  This is a real, permanent limitation until a future HR & Payroll (for
  `Employee`) or Administration (for `User`) module design pass
  deliberately adds a contact field with its own BR justification —
  **not invented here**. Inventing an `Employee.mobile_number` or
  `User.email` column from a Communication-module design pass would be
  exactly the kind of unapproved cross-module schema expansion ADR-007
  §7 and ADR-009 §14 already refused (no BR justifies it the way
  BR-FEE-007 justified `InvoiceLineItem` or BR-TRN-006 justified
  `Driver`/`Trip`).
- **Push channel** (any recipient type) → no push provider is chosen or
  integrated. `dispatch()` on a `Push`-channel log fails immediately
  with `"No push notification provider is configured — Push channel
  dispatch is out of scope."`, before any recipient resolution is
  attempted.

None of these failure paths throw an unhandled exception to the
Controller — every one is caught inside `dispatch()` and converted to
`Failed` + `failure_reason`, then a normal 200 response carrying that
status, exactly like a successful dispatch's response shape.

### f. `dispatch()` is a new, separate, explicit-trigger method — `create()` still only ever queues

`NotificationLogService::dispatch(int $id): NotificationLogResponse` is
new. `create()` is unchanged in shape — it still only inserts a
`Queued` row via `AuditService`, exactly as ADR-010 §2 described.
Queueing and dispatching remain two explicit, separately-triggered
steps, matching `NotificationLog`'s own `Queued → Dispatched →
Delivered/Failed` lifecycle from Appendix-G. No scheduler/cron
infrastructure is introduced — `dispatch()` must be called explicitly,
either by another Service (a future caller that wants "queue then
immediately dispatch" composes both calls itself) or via the
Controller endpoint below, matching every prior "no cron
infrastructure" precedent in this codebase (ADR-007 §4, ADR-009 §5,
ADR-017 §5's explicit-trigger reservation-expiry precedent).

The existing `POST /communication/notification-logs/{id}/dispatch`
endpoint (previously calling `markDispatched()` directly, an
unconditional status flip) now calls the new `dispatch()` instead —
same route, same URL, now doing real work. `markDispatched()` itself is
kept as a private-use building block only reachable internally (no
longer routed) for any future caller that needs to force a status
transition without a real gateway call (e.g. a manual admin override) —
it is not removed since `markDelivered()` still depends on the same
`changeStatus()` helper shape.

### g. Dependency injection — `NotificationLogService` depends on the interfaces, never `Msg91Gateway` directly

`Config\Services::notificationLogService()` now constructs
`NotificationLogService` with `static::smsGateway()` and
`static::emailGateway()` — two new factory methods, each returning
`SmsGatewayInterface`/`EmailGatewayInterface` typed instances backed by
`Msg91Gateway` today. Swapping MSG91 for a different vendor later means
changing only these two factory methods (and adding the new driver
class) — `NotificationLogService`'s constructor, and every existing
caller of `Services::notificationLogService()`, are untouched by a
vendor swap. This is what makes the design genuinely pluggable rather
than pluggable in name only.

## Consequences

- New migration: `2026-08-07-260001_AddMessageBodyToNotificationLogsTable.php`.
- New: `App\Config\Notification`; `App\Modules\Communication\Gateways\SmsGatewayInterface`,
  `EmailGatewayInterface`, `GatewayException`;
  `App\Modules\Communication\Gateways\Msg91\Msg91Gateway`.
- `NotificationLog` entity, `NotificationLogModel`,
  `CreateNotificationLogRequest`, `NotificationLogResponse` all gain
  `message_body`.
- `NotificationLogService` constructor now also takes `GuardianModel`,
  `StudentGuardianLinkModel`, `SmsGatewayInterface`, `EmailGatewayInterface`;
  gains `dispatch(int $id)`.
- `Config\Services::notificationLogService()` updated; new
  `smsGateway()`/`emailGateway()` factory methods added.
- `NotificationLogController::dispatch()` now calls the real
  `dispatch()` method instead of the unconditional `markDispatched()`.
- `AttendanceService::notifyGuardianOfAbsence`,
  `TimetableEntryService::reviseEntry`,
  `SubstitutionService::createSubstitution`,
  `ReservationService::notifyNextInQueue` all now pass a real,
  trigger-specific `message_body`.
- `app/Core/OpenApi/Spec.php`'s `NotificationLogCreateRequest`/
  `NotificationLogResponse` schemas gain `message_body`.
- New tests: `tests/Feature/Communication/NotificationDispatchTest.php`
  (Guardian-direct dispatch, Student-via-primary-Guardian resolution,
  Employee/User documented-failure path, gateway-failure path, Push-
  channel failure path, `message_body` persistence — all offline, via
  `FakeSmsGateway`/`FakeEmailGateway` bound with `Services::injectMock()`)
  and `tests/unit/Communication/Msg91GatewayPayloadTest.php` (payload-
  building only, via reflection, no network call).
- `docs/ADR/ADR-010-communication-and-reports-scope-decisions.md` §1/§2/
  §5's notes are corrected to point here, matching the precedent set for
  every other resolved item's originating ADR.
- `docs/development/School-ERP-Development-Roadmap.md`'s "Immediate next
  action" section is updated: the SMS/Email/Push gateway item is
  removed, a new Stage 17 entry is added.
- Out of scope, unchanged: BR-COM-002/003 (bulk messaging authorization,
  emergency alert override) — dispatch now exists per-notification-log,
  but no bulk-send endpoint or priority path was built; a Push provider
  (no vendor chosen, `Push` dispatch always fails by design); Employee/
  User contact fields (explicitly named as future HR & Payroll/
  Administration module scope, not this ADR's to invent).
