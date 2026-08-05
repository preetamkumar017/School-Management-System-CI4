---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, Company Development Standard §9
---

# Phase 3 — Administration DTO Design

## `LoginRequest`

| Field | Validation rule |
|---|---|
| username | `required` |
| password | `required` |

Deliberately no "remember me" or other flags — session model is the refresh-token lifetime, uniformly (Company Development Standard §9).

## `LoginResponse`

Fields: `access_token`, `refresh_token`, `access_token_expires_at`. Per the Company Development Standard's JWT claim rule, the access token's payload carries `user_id`, `role_id`, `permission_set` only — no PII, no `username`/`owner_ref_id`.

## `RefreshRequest`

| Field | Validation rule |
|---|---|
| refresh_token | `required` |

## `RefreshResponse`

Fields: `access_token`, `access_token_expires_at`. A refresh never issues a new refresh token — the original keeps its own expiry (7 days), matching "one refresh attempt per expired-access-token event, no infinite retry loop" from the archived ASD's still-valid security conventions.

## `LogoutRequest`

No body — the caller's own current refresh token (from the `Authorization`-adjacent context, not a request field) is revoked. `LogoutAllRequest` (no body either) revokes every refresh token for the authenticated user.

## `CreateUserRequest` / `UpdateUserRequest`

| Field | Validation rule |
|---|---|
| username | `required`, max length 50, alphanumeric + dot/underscore |
| password | `required` (create only — update goes through a dedicated `ChangePasswordRequest`, never a plain field edit) |
| role_id | `required` |
| owner_type | `required`, in `{EMPLOYEE, STUDENT, GUARDIAN}` |
| owner_ref_id | `required` |

`UpdateUserRequest` excludes `password`/`owner_type`/`owner_ref_id` — the owner link is immutable post-creation; password changes are a dedicated operation, not a plain field edit, per the Company Development Standard's "password change revokes all sessions" rule needing its own explicit trigger point.

## `ChangePasswordRequest`

| Field | Validation rule |
|---|---|
| current_password | `required` |
| new_password | `required`, meets password policy (length/complexity deferred to implementation) |

## `UserStatusChangeRequest`

| Field | Validation rule |
|---|---|
| status | `required`, in `{ACTIVE, LOCKED, DEACTIVATED}` |

## `UserResponse`

Fields: `user_id`, `username`, `role_id`, `owner_type`, `owner_ref_id`, `status`, `last_login_at`. Never `password_hash`, under any circumstance or caller role.

## `CreateRoleRequest` / `UpdateRoleRequest`

| Field | Validation rule |
|---|---|
| role_name | `required`, max length 50 |
| description | optional, max length 255 |
| permission_set | `required`, array of permission strings |

`is_system_role` excluded from both — set only by initial seed data, never via the API.

## `RoleResponse`

Fields: `role_id`, `role_name`, `description`, `is_system_role`, `permission_set`.

## `AuditLogResponse`

Fields: `audit_log_id`, `entity_name`, `record_id`, `action`, `performed_by`, `performed_at`, `old_value`, `new_value`, `reason`. `ip_address` **excluded** from the default response — Company Development Standard's PII-handling caution; exposed only to a role explicitly permitted to see it (role-scoped variant, not the default shape).
