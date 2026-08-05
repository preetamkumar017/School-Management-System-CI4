---
status: Living reference — amended as new modules are designed
last-updated: 2026-08-06
supersedes: ASD Part-1/2/3/4 (archived, Spring Boot-era); addendum to Appendix-K until Appendix-K is regenerated
references: Company Development Standard §5, Appendix-K API Specification v1.0
---

# School ERP — API Governance Supplement

## Purpose

The Company Development Standard (§5) sets the company-wide API baseline —
response envelope, HTTP method mapping, pagination/filter/sort conventions,
versioning. This document adds only what's specific to School ERP: the
concrete endpoint groups Appendix-K doesn't yet catalogue, and the
governance detail (deprecation lifecycle, performance targets) that used to
live in ASD (now archived, Spring-Boot-era) but wasn't specific enough to
belong in the company-wide standard.

## Appendix-K addendum — endpoint groups not yet in Appendix-K

ADR-001 flagged that Appendix-K's module-wise API catalogue has no Academic
subsection; the same gap now exists for Admission's core CRUD (Appendix-K
predates this design work). Until Appendix-K itself is regenerated, these
Controller Design documents are the authoritative endpoint list:

- **Academic** — `docs/design/academic/Phase-5-Controller-Design.md`
  (`/api/v1/academic/sessions`, `/classes`, `/sections`, `/subjects`,
  `/grading-schemes`, `/class-subject-map`)
- **Admission (core CRUD)** — `docs/design/admission/Phase-5-Controller-Design.md`
  (`/api/v1/admission/applications`, `/seat-allocations`)
- **Admission (Confirm Enrollment)** — `docs/design/admission/Phase-6-Service-Design-Confirm-Enrollment.md`
  (architecture-level only; concrete endpoint still Open, tracked separately)
- **SIS** — `docs/design/sis/Phase-4.7-Controller-Design.md`
  (`/api/v1/sis/students`, `/guardians`, `/student-guardian-links`)

## API lifecycle

Per the Company Development Standard §5: additive changes don't bump the
version; breaking changes do. This project has one live version, `v1`, for
every endpoint group above — no deprecated version exists yet, so there is
nothing to apply a deprecation window to today. When a `v1` endpoint's
contract needs a breaking change:

1. `v2` is introduced for that endpoint group only — not a blanket
   repository-wide version bump.
2. `v1` continues serving unchanged for a deprecation window (minimum one
   release cycle) before retirement; retirement is a deliberate decision,
   not automatic cleanup once `v2` ships.
3. The response envelope's `meta` carries a machine-readable deprecation
   marker on `v1` responses once `v2` exists, so callers can detect it
   without reading changelogs.

## Performance targets (initial, to be revised against real measurements)

No production traffic exists yet to derive these from; these are starting
targets to design against, not measured SLAs — revisit once Hostinger
staging measurements exist:

| Operation class | Target (95th percentile) |
|---|---|
| Simple read (`GET /{id}`, `GET /` on Master data) | < 300 ms |
| List/search with filters (paginated Transaction data) | < 800 ms |
| Write (create/update/status-transition) | < 500 ms |
| Cross-module write (e.g. Confirm Enrollment's Admission→SIS call) | < 1500 ms |
| Report/export generation | Async job — no synchronous target; caller gets a job reference and a completion notification, per the Company Development Standard's async-for-large-exports pattern |

## Rate limiting tiers

Per the Company Development Standard §9 (rate limiting tracked separately
for interactive vs. machine traffic):

| Caller type | Suggested limit | Notes |
|---|---|---|
| Interactive (web/mobile, per authenticated user) | 60 requests/minute | Generous enough for normal UI use; revisit if legitimate bulk-entry screens (e.g. attendance marking) need higher |
| Machine/integration (per API key, once external integrations exist) | 300 requests/minute | No such integration exists yet — placeholder tier, not a designed integration |

## Governance principle

Restated from the archived ASD Part-4 because it's a good practice worth
keeping, not because it's School-ERP-specific: when a conflict exists between
documents, the earlier/more-foundational document wins unless a later
document explicitly and narrowly states it supersedes a named section of the
earlier one. This supplement defers to the Company Development Standard on
every point where the two might otherwise disagree.
