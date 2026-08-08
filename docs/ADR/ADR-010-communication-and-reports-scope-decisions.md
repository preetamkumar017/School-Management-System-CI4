---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-009 record)
relates-to: Appendix-C v1.1 (BR-COM-001–005, BR-RPT-001–005); Appendix-E (FR-37–42); ADR-006 §6/§9 (Timetable/Attendance notification seams, closed here)
---

# ADR-010: Communication and Reports module scope — the last two Stage 6 modules

## Context

Communication (`ENT-COM-001` `Circular`, `ENT-COM-002` `NotificationLog`)
and Reports (no entities — `docs/design/School-ERP-Module-Architecture.md`
already records "None (read-only; aggregates across other modules'
Service classes, never their Models directly)") are the last two
undesigned modules on the roadmap. Bundled here since Reports has no
domain model of its own to design — its whole shape is "what can
Communication and every other already-shipped module's Service layer
already answer."

Communication's FK graph is clean: `Circular.author_id → User`
(Administration, built), `NotificationLog.recipient_ref_id` is
polymorphic against `Guardian` (SIS), `Employee` (HR & Payroll), or
`User` (Administration) — all three exist. No undesigned-module stub is
needed, the second module in a row after Library/Transport (ADR-009).

Two things block the *full* scope Appendix-C/E describe, and neither is
invented here:

1. FR-37's actual multi-channel dispatch (SMS/Email/Push) needs a
   gateway vendor integration explicitly marked "Client/Product Decision
   Required" in Appendix-E — no HTTP client for any such gateway exists
   anywhere in this codebase.
2. FR-39's two-way teacher-parent messaging needs a `Message` entity
   that doesn't exist anywhere in Appendix-G's Communication catalogue
   (only `Circular` and `NotificationLog` are modeled).

Reports' FR-40/41/42 (role-scoped dashboards, a custom field-selection
report builder, trend analytics, Excel/PDF export) assume: (a) a
field/report-level authorization configuration that doesn't exist
(`Configuration` is deferred, same gap ADR-005/006/008 already named),
(b) an Excel/PDF generation library — this codebase has none (no
`phpoffice/phpspreadsheet`, no `dompdf`, nothing in `composer.json`), and
(c) genuine cross-module aggregate queries (total fee collected, a
school-wide attendance percentage) that don't exist on the relevant
source modules' Services today — only scoped listings (`listByStudent`,
`listByInvoice`, etc.), not list-all/sum-all. Adding those aggregate
methods to five already-shipped modules speculatively, just to make a
Reports dashboard look fuller, would silently expand every one of those
modules' approved scope from a different module's design pass — the same
reasoning ADR-009 §13 already used to refuse a unilateral Fees change
from Transport's side.

## Decision

### 1. `Circular` — implemented per Appendix-G, with one decided additive column

Maps directly onto its attribute catalogue. `author_id` is validated via
Administration's `UserService::getUser()`. `target_audience` is stored
as a plain string (Appendix-G's own `VARCHAR(50)`, no FK), validated
only for non-empty — Appendix-C's "must reference a valid Class/Section
or 'All'" is **not** cross-checked against real `Class`/`Section` rows:
neither `ClassService` nor `SectionService` exposes a name-based lookup
today, and adding one solely for this would be the same kind of
unilateral cross-module expansion §13 above already refuses. A decided
additive column, `status` (`Posted`/`Retracted`, not in Appendix-G's
literal attribute list), supports the Lifecycle line's own "Retracted"
state that no column otherwise carries — the same kind of decided
addition as `BookIssue.status` (ADR-009 §1).

### 2. `NotificationLog` — implemented as a log/record-keeping entity, not a live dispatcher

Maps onto its attribute catalogue. `recipient_ref_id` is validated
against SIS's `GuardianService`, HR & Payroll's `EmployeeService`, or
Administration's `UserService`, dispatched on `recipient_type` — the
second polymorphic-FK-against-multiple-live-modules validation this
codebase has done (after `BookIssue`, ADR-009 §1). `NotificationLogService`
offers `create` (logs a `Queued` attempt), `markDispatched`,
`markDelivered`, `markFailed` (BR-COM-004 — reason required, logged via
`AuditLog::ACTION_OVERRIDE`) — actual dispatch to an SMS/Email/Push
gateway is **not implemented** (Context, item 1). This is deliberately
the same shape as Fees'/HR's "compute and store, don't post externally"
precedent (ADR-009 §3/§4) — a caller records that a notification was
queued/attempted; nothing here calls out to a real channel.

> **Resolved by [ADR-021](ADR-021-communication-sms-email-gateway.md)**
> (2026-08-07): MSG91 chosen as the first gateway vendor, behind a
> pluggable `SmsGatewayInterface`/`EmailGatewayInterface` design; a new
> `NotificationLogService::dispatch()` performs real dispatch for
> Guardian-direct and Student-via-primary-Guardian recipients.
> Employee/User recipients remain genuinely unsupported (no contact
> field in Appendix-G) and fail with a documented reason rather than
> silently succeeding.

### 3. Closes two of ADR-006's remaining notification seams

`TimetableEntryService::reviseEntry` (BR-TT-005) and
`AttendanceService::markAttendance` when `state = ABSENT`
(BR-ATT-004) now call `NotificationLogService::create()` to log a
`Queued` notification — a small, targeted change to already-shipped
Stage 6b code, the same shape ADR-006 §11/ADR-008 §2 already used for
closing a prior stage's stub. This closes the *logging* half of both
seams; the *actual delivery* half stays open per item 2 above, now
correctly attributed to "no gateway integration" rather than "no
notification-log entity" (which no longer applies).

### 4. BR-COM-001 (Relationship-Based Messaging Restriction) is out of scope

Needs a `Message` entity for direct teacher-parent messaging that
doesn't exist anywhere in Appendix-G (Context, item 2). Not implemented
— no placeholder table, matching every prior "no approved entity, don't
invent one" precedent (ADR-006 §3, ADR-008 §9, ADR-009 §7).

### 5. BR-COM-002/003 (Bulk Messaging Authorization, Emergency Alert Override) are out of scope

Both govern the actual multi-channel dispatch mechanism (Context, item
1), which isn't implemented. Not implemented here either — no bulk-send
endpoint, no emergency-alert priority path, since there is no dispatch
to prioritize or gate.

> **Partially resolved by [ADR-021](ADR-021-communication-sms-email-gateway.md)**
> (2026-08-07): real per-notification-log dispatch now exists (§2's
> update above), which was the missing prerequisite named here — but
> BR-COM-002/003 themselves (a bulk-send endpoint, an emergency-alert
> priority path) are still not built; ADR-021 only closes the dispatch-
> mechanism gap this section pointed at, not these two BRs.

### 6. BR-COM-005 (Communication Retention Period) is not separately implemented

"Client/Product Decision Required" per Appendix-C, no purge-job
infrastructure exists anywhere in this codebase — every record in this
system defaults to indefinite soft-deleted retention already (the
Company Development Standard's own baseline), which already satisfies
Appendix-C's stated fallback ("records default to indefinite retention
pending configuration rather than being purged prematurely"). Nothing
new to build.

### 7. Reports — a single composed summary endpoint, built only from existing Service methods

`App\Modules\Reports\Services\ReportsService::getSummary()` composes
counts from every already-shipped module's own list-all Service method
(`UserService::listUsers`, `ClassService::listClasses`,
`AcademicSessionService::listSessions`, `DepartmentService::
listDepartments`, `DesignationService::listDesignations`,
`EmployeeService::listEmployees`, `BookService::listBooks`,
`VehicleService::listVehicles`, `RouteService::listRoutes`,
`FeeHeadService::listFeeHeads`) into a single response — no new query
method is added to any other module's Service to support this (Context).
Includes `generated_at` (BR-RPT-005, `Time::now()` at request time — no
caching/background refresh job exists, so "last refresh" is always
"now"). No `provisional` labelling is computed (BR-RPT-004) — the
summary is pure master-data counts, not exam/attendance/fee figures with
a lock-status to check.

> **Extended by [ADR-022](ADR-022-reports-dashboard.md)** (2026-08-08):
> four real report areas (Fee collection, Attendance, Admissions funnel,
> Academic performance) with genuine aggregate query methods added to
> their owning modules, plus PDF/Excel export — additive alongside this
> `getSummary()`, which is unchanged.

### 8. FR-40's role-scoped dashboards, FR-41's custom report builder, FR-42's trend analytics, and BR-RPT-001/002/003/004 are out of scope

Role-gated widget visibility, ad-hoc field selection, Excel/PDF export,
and historical report versioning all need infrastructure this codebase
doesn't have (Context) — export role-gating (BR-RPT-001) is also not
enforced anywhere else in this codebase (`PermissionChecker` has never
been wired into a Controller, ADR-007 §8's precedent), so it isn't
introduced newly here either. A future dedicated Reports pass, once
real dashboard requirements are scoped, is the right place to add
genuine aggregate query methods to the specific source modules that need
them — not this pass, speculatively, across five modules at once.

## Consequences

- `docs/design/School-ERP-Module-Architecture.md`'s Communication and
  Reports rows are updated to Designed, citing this ADR.
- `docs/design/communication/` and `docs/design/reports/` (this ADR's
  Phase docs) proceed on the basis of every decision above; none are
  re-derived there.
- `App\Modules\Timetable\Services\TimetableEntryService` and
  `App\Modules\Attendance\Services\AttendanceService` change in this same
  pass to call `NotificationLogService::create()` (§3) — small targeted
  changes to already-shipped code, not new module boundary violations
  (both already depend on cross-module Services).
- A future Communication follow-up must account for: a real SMS/Email/
  Push gateway integration once a vendor is chosen (§1's dispatch gap,
  §2, §5), and a `Message` entity for two-way messaging (§4).
- A future Reports follow-up must account for: genuine per-module
  aggregate query methods (fee collection totals, attendance
  percentages, etc.) added to the *owning* module when a real dashboard
  requirement is scoped, an Excel/PDF export library, and a
  `Configuration`-driven field/report authorization model (joining the
  `Configuration` candidates ADR-005/006/008 already keep).

> **Resolved (in part) by [ADR-022](ADR-022-reports-dashboard.md)**
> (2026-08-08): the aggregate-query-methods gap and the Excel/PDF
> export library gap are both closed, for four specific, user-scoped
> report areas — Fee collection, Attendance, Admissions funnel, Academic
> performance. FR-40's role-scoped dashboards, FR-41's custom report
> builder, FR-42's trend analytics, and the `Configuration`-driven
> field/report authorization model remain open, unresolved by ADR-022.
