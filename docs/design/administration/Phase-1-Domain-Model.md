---
status: Approved (Original)
last-updated: 2026-08-06
references: Appendix-G Data Dictionary v1.0 (SYS module entities), Company Development Standard §9, School-ERP-Development-Roadmap.md
---

# Phase 1 — Administration Domain Model (minimal slice: User, Role, AuditLog)

## Scope

Per the Development Roadmap's Stage 1: only `User`, `Role`, and `AuditLog`
are designed now — the three entities `App\Core`'s Auth/RBAC/Audit
infrastructure needs to exist against real tables. `Configuration`,
`Document`, and `ApprovalRequest` (also Administration-owned per
`School-ERP-Module-Architecture.md`) are deliberately deferred to a later
design pass, closer to when a consuming module actually needs them.

Field lists below are taken directly from Appendix-G's SYS module entity
cards. One supporting table not in Appendix-G is added: `refresh_tokens` —
an infrastructure necessity for the Company Development Standard's §9
requirement that refresh-token state be server-tracked for explicit
revocation; it is not a business entity and carries no `ENT-*` ID.

---

## Entity: `User` (ENT-SYS-001, table `users`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| username | VARCHAR(50) | N | – | Unique, alphanumeric + dot/underscore |
| password_hash | VARCHAR(255) | N | – | bcrypt/argon2 hash (Company Development Standard §9); never plaintext, never returned in any response |
| role_id | BIGINT UNSIGNED | N | – | FK → `roles`, RESTRICT |
| owner_type | enum (`EMPLOYEE`, `STUDENT`, `GUARDIAN`) | N | – | Polymorphic discriminator |
| owner_ref_id | BIGINT UNSIGNED | N | – | Polymorphic FK — no DB-level FK (Company Development Standard §4.6); resolves to HR's `employees` (not yet designed), SIS's `students`, or SIS's `guardians` depending on `owner_type`; integrity checked in the Service layer, not the database |
| status | enum (`ACTIVE`, `LOCKED`, `DEACTIVATED`) | N | ACTIVE | BR-HR-002 |
| failed_login_count | INT | N | 0 | Reset on successful login; lockout at 5 (Company Development Standard §9) |
| last_login_at | DATETIME | Y | NULL | – |

Unique constraint: `username`. No FK to `owner_ref_id` (polymorphic, app-layer integrity only). `email`, referenced by the Attribute Catalogue's uniqueness note in Appendix-G, is **not** a `User` column — email is owned by whichever entity `owner_ref_id` resolves to (`Guardian.email`, etc.); `User` itself has no email field of its own.

### `User` Lifecycle

Created at account provisioning → `ACTIVE` → `LOCKED` (5 consecutive failed attempts, Company Development Standard §9) → reset to `ACTIVE` on successful subsequent login or IT Admin unlock → `DEACTIVATED` (on exit/access revocation, BR-HR-002) → archived per the retention policy (`School-ERP-Database-Supplement.md`).

---

## Entity: `Role` (ENT-SYS-002, table `roles`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| role_name | VARCHAR(50) | N | – | Unique (e.g. `Finance Team`) |
| description | VARCHAR(255) | Y | NULL | – |
| is_system_role | BOOLEAN | N | FALSE | Protected system-default roles cannot be deleted (Service-layer check) |
| permission_set | JSON | N | `{}` | Array of permission action strings (e.g. `["read","create"]`); Appendix-G notes this could alternatively be a `role_permission` junction table — **decided**: JSON column, not a junction table. A role's permission set has no attributes of its own beyond membership, so a full relational junction adds a layer nothing needs; JSON is consistent with the Company Development Standard's guidance to use JSON only where it's genuinely simpler, which this is. |

Unique constraint: `role_name`. No foreign keys. Relationships: one-to-many with `User`.

### `Role` Lifecycle

Created at system setup → Active → Modified (permission changes, logged to `AuditLog`) → rarely deprecated (a `Role` referenced by any `User` cannot be hard-deleted; RESTRICT applies).

---

## Entity: `AuditLog` (ENT-SYS-004, table `audit_logs`)

Does **not** extend `BaseEntity`'s mutable-record assumptions — write-once,
no `UPDATE`/`DELETE` code path exists against it at all (Company Development
Standard §4.10), enforced at the application layer (no Service method to
mutate or remove a row) and, where the hosting environment allows it, at the
DB-privilege level too.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| audit_log_id | BIGINT UNSIGNED (PK) | N | – | Surrogate, auto-increment |
| entity_name | VARCHAR(50) | N | – | Name of the audited entity/table (e.g. `Invoice`) |
| record_id | BIGINT UNSIGNED | N | – | PK value of the audited record — polymorphic alongside `entity_name`, no DB-level FK |
| action | enum (`CREATE`, `UPDATE`, `DELETE`, `APPROVE`, `REJECT`, `OVERRIDE`) | N | – | |
| performed_by | BIGINT UNSIGNED | N | – | FK → `users`, RESTRICT |
| performed_at | DATETIME | N | CURRENT_TIMESTAMP | |
| old_value | JSON | Y | NULL | Prior field value(s), serialized; Sensitive (context-dependent, may hold PII) |
| new_value | JSON | Y | NULL | New field value(s), serialized; Sensitive (context-dependent) |
| ip_address | VARCHAR(45) | Y | NULL | Valid IPv4/IPv6 |
| reason | VARCHAR(255) | Y | NULL | Mandatory when `action = OVERRIDE` (Service-layer conditional-required check, not a DB constraint) |

Indexes: composite on `(entity_name, record_id)`, plus separate indexes on `performed_by` and `performed_at`.

### `AuditLog` Lifecycle

Created once, per audited action, as the second-to-last step of the operation it records (immediately before any event is raised) — never updated, never deleted under normal operation. Governs every other module's own write path, not just Administration's.

---

## Supporting table (not an Appendix-G entity): `refresh_tokens`

| Field | Type | Null | Default | Constraint |
|---|---|---|---|---|
| refresh_token_id | BIGINT UNSIGNED (PK) | N | – | Surrogate |
| user_id | BIGINT UNSIGNED | N | – | FK → `users`, RESTRICT |
| token_hash | VARCHAR(255) | N | – | Hash of the refresh token, never the raw token |
| expires_at | DATETIME | N | – | |
| revoked_at | DATETIME | Y | NULL | Set on logout, password change, or access revocation |
| created_at | DATETIME | N | CURRENT_TIMESTAMP | |

Exists solely to satisfy the Company Development Standard's §9 requirement
that refresh-token state be server-tracked for explicit revocation — a
technical necessity, not a business entity, so it carries no `ENT-*` ID and
isn't in Appendix-G. Index on `user_id`; index on `token_hash` for fast
lookup at refresh time.

## Out of scope

- `Configuration`, `Document`, `ApprovalRequest` — deferred (see Scope above).
- `Employee` (HR & Payroll's entity, referenced polymorphically by `User.owner_ref_id`) — not designed here; HR & Payroll's own future design owns it.
