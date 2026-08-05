---
status: Living reference
last-updated: 2026-08-06
supersedes: DDD Part-1/2/3/4 (archived, Spring Boot/PostgreSQL-era)
references: Company Development Standard §4, Appendix-G Data Dictionary v1.0, Appendix-F NFR Catalogue
---

# School ERP — Database Supplement

## Purpose

The Company Development Standard (§4) sets the company-wide database
baseline — naming, common columns, soft-delete, constraint-to-layer rule,
migrations. This document adds only what's specific to School ERP and was
previously scattered across DDD Part-1 through Part-4 (now archived,
written for PostgreSQL/Spring Boot). It does not repeat anything the
Company Development Standard already covers.

## MySQL 8 adaptations from the archived DDD

The archived DDD was written against PostgreSQL. Two adaptations for MySQL 8,
noted here so they aren't silently re-decided per table later:

- **JSON columns**: MySQL 8's native `JSON` type is the equivalent of
  PostgreSQL's `JSONB` (used by e.g. `GradingScheme.grade_band_json`,
  `Role.permission_set`). No functional loss for this project's use of JSON
  columns (configuration blobs, not queried by JSON path in bulk).
- **Partial/conditional unique indexes**: MySQL has no direct equivalent to
  PostgreSQL's partial unique index. Where a unique constraint only applies
  conditionally (e.g. "unique among non-deleted rows"), the standard MySQL 8
  workaround is a generated column that evaluates to the constrained value
  when the condition holds and `NULL` otherwise, with a unique index on the
  generated column (MySQL treats multiple `NULL`s as distinct, so soft-deleted
  rows don't collide). Apply this pattern wherever a table needs a
  soft-delete-aware unique constraint, rather than re-deriving the workaround
  per table.

## Partitioning candidates

The Company Development Standard (§4) states high-volume tables should be
partitioned by a natural time/session boundary; it does not name which
tables. Based on Appendix-G's Data Category column, these are School ERP's
partitioning candidates once volume warrants it (not a mandate to partition
from day one — a schema-creation-time decision made when the table is built,
informed by this list):

| Table | Partition key | Rationale |
|---|---|---|
| `attendance_records` | `academic_session_id` (RANGE, MySQL 8 native RANGE partitioning by a stored session-boundary column) | One row per student per period per day — the highest-volume table in the system |
| `staff_attendance_records` | `academic_session_id` | Same shape, staff-scoped |
| `audit_logs` | `created_at` (RANGE, by year or by academic-session boundary) | Centralized, write-once, per Company Development Standard §4.10 — grows unbounded by definition |
| `notification_logs` | `created_at` | High write volume, no cross-partition query pattern expected |
| `marks_records` | `academic_session_id` (once Examination is designed) | One row per student per subject per exam |
| `invoices` / `payments` | `academic_session_id` (once Fees is designed) | Financial transaction volume scales with student count × fee heads |

`timetable_entries` is explicitly "versioned per term" per Appendix-G — this
is a scoping/versioning question for Timetable's own design, not a
partitioning one; not included above.

## Archival trigger — decided 2026-08-06

Appendix-G flags nearly every entity's Archival Policy field identically:
*"Archived per Appendix-F NFR-ARC-001 after academic-session close or record
inactivity threshold (Client/Product Decision Required for exact trigger)."*
This is the same open question repeated dozens of times across the data
dictionary, not dozens of separate decisions — resolved once, here, rather
than left to be re-opened per entity as each module gets designed.

**What's fixed (unchanged):** archival is move-to-cheaper-storage-but-still-
queryable, never deletion (Company Development Standard §4).

**Decision — retention/archival thresholds:**

| Data category | Becomes archival-eligible | Rationale |
|---|---|---|
| General master/transactional data (Attendance, Timetable, most Academic/Examination records) | 3 years after academic-session close, or 3 years of record inactivity, whichever comes first | A generous-but-bounded default; most operational queries need at most the last 2–3 academic years live |
| Financial records (`Invoice`, `Payment`, `PayrollRun`) | 7 years after the record's academic session closes | Matches the commonly-cited 7-year retention window for financial/tax-relevant records in India; not verified against a specific statute — a reasonable default, not compliance advice |
| `Student`/`Guardian` (PII, exit-triggered) | 7 years after the student's `EXITED`/`ARCHIVED` transition, not after session close | Aligned with the financial window for consistency; covers typical school-leaving-certificate re-issuance requests, per Appendix-G's own note that Student retention is "typically several years post-exit for certificate re-issuance" |
| `AuditLog` | Never archival-eligible in the sense of leaving the primary schema — partitioned (see above), retained permanently, write-once | Unchanged from the Company Development Standard's audit rule |

**This is a default, not verified legal/compliance research.** It resolves
the documentation gap so implementation isn't blocked, and it's a reasonable
starting position for an Indian school-ERP context (hence the 7-year
financial-record figure), but actual statutory retention obligations should
be confirmed with the business/legal counsel before this becomes load-bearing
for a real compliance audit. Treat this table as the working default; revise
it via an ADR if a real requirement supersedes it, rather than silently
diverging per module.

## Constraint-to-layer rule — restated, not redefined

Company Development Standard §4 item 8 already fixes this; restated here
only because DDD Part-3's version of the same table was the most-cited
section in the archived documents and future contributors will look for it
under a DDD-shaped heading:

| Rule type | Enforced at |
|---|---|
| PK / FK / Unique / single-row Check | Database |
| Cross-table or aggregate rule | Application Service layer — never a DB trigger |

A rule not cleanly assignable to one of these rows is incompletely specified
— see the Company Development Standard for the full statement.
