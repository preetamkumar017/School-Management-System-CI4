---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 — Domain Model, Phase 2 — Model Design
---

# Phase 3 — Admission DTO Design

Convention: one Response DTO per entity; a `Create…Request` plus dedicated transition DTOs for each forward-only status change (mirroring how SIS modeled `Student`'s own forward-only status field — a single generic `UpdateApplicationRequest` covering every field would blur which fields are actually editable at which lifecycle stage).

## `CreateApplicationRequest`

| Field | Validation rule |
|---|---|
| applicant_name | `required`, max length 100 |
| dob | `required`, valid past date |
| class_applied_id | `required` (validated against Academic's `ClassService`, not a local table) |
| aadhaar_number | optional, exactly 12 digits, checksum-valid |
| category | `required`, in `{GENERAL, RTE}` |

`application_reference_no` and `status` excluded — system-generated/system-managed, never client-supplied.

## `ApplicationVerifyRequest`, `ApplicationShortlistRequest`, `ApplicationWaitlistRequest`, `ApplicationRejectRequest`

No body fields beyond the implicit path `{id}` — each is a bare status transition (`SUBMITTED → VERIFIED`, `→ SHORTLISTED`, `→ WAITLISTED`, `→ REJECTED`). Kept as distinct request types (rather than one generic `ApplicationStatusChangeRequest` accepting an arbitrary target status) so each endpoint's allowed source/target transition is explicit at the type level, not re-derived from a runtime status-transition table on every call.

The `SUBMITTED/VERIFIED/... → ADMITTED` transition is **not** one of these — it belongs to FR-02 Confirm Enrollment, already designed in Phase 6, and is intentionally not duplicated here.

## `ApplicationResponse`

Fields: `application_id`, `application_reference_no`, `applicant_name`, `dob`, `class_applied_id`, `aadhaar_number`, `category`, `status`, `submitted_at`, `decided_at`.

## `CreateSeatAllocationRequest` / `UpdateSeatAllocationCapacityRequest`

| Field | Validation rule |
|---|---|
| class_id | `required` (create only — immutable after creation, same reasoning as Academic's `Section.class_id`) |
| academic_session_id | `required` (create only — immutable after creation) |
| total_capacity | `required`, positive integer |
| rte_quota_capacity | `required`, ≤ 25% of `total_capacity` (cross-field — Service-layer check, not a DB constraint) |

`seats_filled`/`rte_seats_filled` excluded from both — never client-set; only ever changed by `ApplicationModel::incrementSeatsFilled` as a side effect of confirmed admissions.

## `SeatAllocationResponse`

Fields: `seat_allocation_id`, `class_id`, `academic_session_id`, `total_capacity`, `rte_quota_capacity`, `seats_filled`, `rte_seats_filled`.
