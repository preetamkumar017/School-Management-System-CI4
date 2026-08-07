---
status: Approved (Original)
last-updated: 2026-08-07
references: Appendix-G Data Dictionary v1.0 (COM module entities), Appendix-C v1.1 (BR-COM-001–005), ADR-010
---

# Phase 1 — Communication Domain Model

## Scope

Per ADR-010: `Communication` (`App\Modules\Communication`) owns two
entities: `Circular`, `NotificationLog` (both Transaction). BR-COM-001/
002/003 (direct messaging, bulk authorization, emergency override) are
out of scope (ADR-010 §4, §5) — no gateway integration, no `Message`
entity.

## Entity: `Circular` (ENT-COM-001, table `circulars`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| author_id | BIGINT UNSIGNED | N | – | FK → Administration's `users` (cross-module, plain FK, validated via `UserService`) |
| post_type | enum (`Homework`, `Circular`, `Announcement`) | N | – | |
| title | VARCHAR(150) | N | – | Non-empty |
| body | TEXT | N | – | Non-empty |
| target_audience | VARCHAR(50) | N | – | Non-empty; not cross-checked against real Class/Section rows (ADR-010 §1) |
| posted_at | DATETIME | N | CURRENT_TIMESTAMP | |
| status | enum (`Posted`, `Retracted`) | N | Posted | Decided additive column (ADR-010 §1) |

No unique constraint (per Appendix-G).

### Lifecycle

Created (Posted) → Retracted (`CircularService::retract`) → Archived.
"Visible"/"Expired" from Appendix-G's own Lifecycle line are not
separately modeled — `status = Posted` and `posted_at` in the past is
"Visible"; no scheduled-visibility or expiry mechanism exists.

---

## Entity: `NotificationLog` (ENT-COM-002, table `notification_logs`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| recipient_type | enum (`Guardian`, `Employee`, `User`) | N | – | |
| recipient_ref_id | BIGINT UNSIGNED | N | – | Polymorphic; validated via SIS `GuardianService`, HR & Payroll `EmployeeService`, or Administration `UserService` per `recipient_type` (cross-module, plain field, no DB FK) |
| channel | enum (`SMS`, `Email`, `Push`) | N | – | |
| trigger_event | VARCHAR(50) | N | – | Free text (e.g. "BR-TT-005 revision", "BR-ATT-004 absence") |
| status | enum (`Queued`, `Dispatched`, `Delivered`, `Failed`) | N | Queued | BR-COM-004 |
| dispatched_at | DATETIME | Y | NULL | |
| failure_reason | TEXT | Y | NULL | Decided additive column, BR-COM-004 — Appendix-G's attribute catalogue has no field to carry the "failure reason" its own Business Rule Statement requires |

No unique constraint (per Appendix-G).

### Lifecycle

Created (Queued) → Dispatched → Delivered/Failed (`failure_reason`
required, logged via `AuditLog::ACTION_OVERRIDE`, BR-COM-004) →
Archived. No live gateway dispatch exists (ADR-010 §2) — every status
transition here is caller-driven, not gateway-driven.

## Out of scope

- BR-COM-001 direct teacher-parent messaging (ADR-010 §4 — no `Message`
  entity).
- BR-COM-002/003 bulk-send authorization / emergency override (ADR-010
  §5 — no dispatch mechanism to gate).
- Live SMS/Email/Push dispatch to any gateway (ADR-010 §1, §2).
