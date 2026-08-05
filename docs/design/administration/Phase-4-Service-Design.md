---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, Phase 3, Company Development Standard §9 (Security)
---

# Phase 4 — Administration Service Design

## `AuthService`

| Operation | Behavior |
|---|---|
| `login(LoginRequest $request): LoginResponse` | Looks up `User` by `username`. If not found, or password doesn't match: increment `failed_login_count`; if the increment reaches 5, set `status = LOCKED`. **Response is identical in both cases** ("invalid credentials") — no distinction between "user doesn't exist" and "wrong password," per the anti-enumeration convention already established for this project. A `LOCKED` user gets a distinct response ("account locked") — this is the one case where the response *does* differ, since it's not username-guessing information, it's telling a legitimate user why they're blocked. On success: reset `failed_login_count` to 0, set `last_login_at`, issue an access token (15 min) and a refresh token (7 days, hashed and stored via `RefreshTokenModel`). |
| `refresh(RefreshRequest $request): RefreshResponse` | Looks up the refresh token by hash via `findValidByTokenHash`. Expired or revoked → reject, caller must log in again (no silent bypass). Valid → issue a new access token only; the refresh token itself is untouched (keeps its original 7-day expiry). |
| `logout(int $userId, string $refreshTokenHash): void` | Revokes the one refresh token presented. |
| `logoutAll(int $userId): void` | Revokes every refresh token for the user — called explicitly, and also called internally by `changePassword` (below), never something a caller invokes separately from a password change and expects partial effect. |
| `changePassword(int $userId, ChangePasswordRequest $request): void` | Verifies `current_password`, hashes and stores `new_password`, then calls `logoutAll` — "password change revokes all sessions" is not optional or caller-controlled. |

JWT payload (access token): `user_id`, `role_id`, `permission_set` — claims only, no PII, per the Company Development Standard's JWT rule. RBAC checks elsewhere in the system read the permission set from the validated token, not by re-querying `Role` on every request (the token *is* the cache; a `Role` permission change takes effect on that user's *next* login/refresh, not retroactively on already-issued access tokens — a documented, deliberate latency, not a bug, given 15-minute access-token lifetime bounds how stale it can get).

## `UserService`

| Operation | Behavior |
|---|---|
| `createUser(CreateUserRequest $request): UserResponse` | Validates `username` uniqueness, that `role_id` exists, and that `(owner_type, owner_ref_id)` doesn't already have a `User` (via `findByOwner`) — one login per owner. Hashes the password. |
| `updateUser(int $id, UpdateUserRequest $request): UserResponse` | `username`/`role_id` only. |
| `changeStatus(int $id, UserStatusChangeRequest $request): UserResponse` | `ACTIVE ⇄ LOCKED ⇄ DEACTIVATED`, not strictly forward-only (unlike `Student`/`Application`) — an IT Admin can unlock a `LOCKED` account, and a `DEACTIVATED` user is a terminal state only by convention, not a DB constraint, since a deactivated staff member's access might legitimately be reinstated. Transitioning to `DEACTIVATED` calls `AuthService::logoutAll`. |
| `getUser(int $id): UserResponse` | Plain read. |
| `listUsers(?string $status = null): array` | Paginated (Transaction-classified — `User` rows accumulate with every staff/parent/student account). |

## `RoleService`

| Operation | Behavior |
|---|---|
| `createRole`, `updateRole`, `getRole`, `listRoles` | Plain CRUD, uniqueness on `role_name`. |
| `deleteRole(int $id): void` | Rejects (Business Rule error) if `isReferencedByAnyUser` or `is_system_role` — RESTRICT, not a soft-delete-then-ignore. |

## `AuditService`

| Operation | Behavior |
|---|---|
| `record(string $entityName, int $recordId, string $action, int $performedBy, ?array $oldValue, ?array $newValue, ?string $reason = null): void` | The single write path every other module's Service layer calls — never `AuditLogModel::insert` directly from outside Administration. Called as the second-to-last step of the operation it documents, immediately before any event is raised, per the Company Development Standard's audit-placement rule; if this call throws, the caller's whole operation must fail (same transaction), not proceed with an unaudited state change. `reason` required when `action = OVERRIDE`, enforced here — not by the database. |
| `getHistoryFor(string $entityName, int $recordId): array` | Powers every module's "view history" screen. |
| `getActivityFor(int $userId, ?string $fromDate = null, ?string $toDate = null): array` | Paginated. |

Every other module's Services call `AuditService::record()` directly — never `AuditLogModel` — this is Administration's one Service-only cross-module contract, same rule as every other module.

## Cross-module exposure

`AuthService`, `UserService`, `RoleService`, `AuditService` are called by every other module (for auth checks, audit writes) via their public methods only. Administration depends on nothing else.
