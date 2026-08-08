---
status: Active
last-updated: 2026-08-08
references: School-ERP-Development-Roadmap.md
---

# Backend — Outstanding Items

Backend functional scope is complete as of Stage 19 (227 passing tests,
`main` pushed) — every module in Appendix-G's Data Dictionary is real,
tested, working code. This doc tracks what's deliberately left before
calling the backend production-ready. Frontend work starts now (see
`School-ERP-Development-Roadmap.md`); revisit this list before a real
deploy or whenever picking backend work back up.

## 1. GPS live-tracking (BR-TRN-003, Transport) — blocked on vendor/hardware

Not started. Needs a GPS tracking device/provider decision (which
hardware, which vendor API, push-webhook vs. poll) before any design or
implementation can begin. Everything else in Transport (Route, Vehicle,
Driver, Trip, TransportAllocation) is done; this is the one named gap.

## 2. RBAC — Phase 1 done (Administration + HR & Payroll), Phase 2 in progress

2026-08-08: a real exploit was demonstrated live (an `Employee`-role
login could edit another employee's salary, apply leave on someone
else's behalf, and list every `User` account) and closed the same day
via `docs/ADR/ADR-024-systemwide-rbac-enforcement.md` — a two-tier
model (`<module>.manage` permission, or ownership of the record) backed
by a shared `App\Core\Authz\ModuleAuthorizer`. **Phase 1 covers
Administration and HR & Payroll** (the two modules the exploit
spanned) — real-server-verified closed, 246 passing tests. **Phase 2
(remaining 11 modules — Academic, Admission, SIS, Examination,
Timetable, Attendance, Fees, Library, Transport, Communication,
Reports) is the same pattern, not yet applied** — every write endpoint
outside Administration/HR & Payroll still relies on authentication
alone. Continue from ADR-024's own per-module table once resumed.

## 3. MSG91 gateway — wired but not credentialed

`Config\Notification` reads `notification.msg91.authKey`/`senderId`
from `.env` — currently empty defaults, so `dispatch()` calls fail
gracefully (marked `Failed`, no crash) rather than actually sending.
Real SMS/Email won't go out until a real MSG91 account + authkey is
added to `backend/.env`. No code change needed, just real credentials
when the school is ready to go live with notifications.

## 4. PHP version mismatch — dev vs. target

`docs/COMPANY_DEVELOPMENT_STANDARD.md` targets PHP 8.3+; local dev has
run on 8.2.29 the whole way through (works fine, CI4 supports it).
Before deploying to Hostinger: confirm Hostinger's actually available
PHP version and either upgrade local dev to match or adjust the
standard's stated target — don't assume 8.3 is available without
checking.

## 5. Deployment — never done

Everything so far is local dev + automated tests only. No deploy to
Hostinger (or anywhere) has happened. When that time comes: env vars,
production DB setup, `composer install --no-dev`, file-storage path
(`writable/uploads/`) permissions on shared hosting, and a real review
of anything currently only validated locally.

## 6. Local dev DB (`school_erp_dev`) needs `php spark migrate --all` periodically

The automated test suite runs against `school_erp_test`, which each
test class migrates fresh — so a stage can ship with 100% passing tests
while the separate `school_erp_dev` database (used for manual smoke
testing and now the frontend) silently falls behind. Hit this for real
2026-08-08: six migrations from Stages 15-19 (Driver/Trip tables,
`invoice_line_items`, notification `message_body`, student
`photo_document_id`) had never been applied to `school_erp_dev`,
causing a real `DatabaseException` on invoice generation until
`php spark migrate --all` was run. Run that command after pulling any
backend change before doing manual/frontend testing against the dev DB.

## Not on this list (already fully resolved, no action needed)

BR-EXM-007 (board affiliation — already a per-`GradingScheme` column),
BR-HR-002 (exit SLA — deactivation is synchronous, no SLA needed),
BR-HR-005 (PF/ESI/PT slabs — caller-supplied by design, ADR-008 §8),
BR-FEE-008 (overdue threshold — already enforced via `due_date`
comparison). These were named in earlier surveys but need no further
backend work.
