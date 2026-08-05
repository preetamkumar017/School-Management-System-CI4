---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 through Phase 4
---

# Phase 5 — Administration Controller Design

Convention: one CI4 Controller per aggregate, extending `App\Core\BaseController`, base path `/api/v1/administration/...` (except Auth, which is unversioned-adjacent at `/api/v1/auth/...` since it's the one set of endpoints reachable without an existing access token).

## `AuthController` — base path `/api/v1/auth`

| Endpoint | Method / URI | Service method | Notes |
|---|---|---|---|
| Login | `POST /login` | `AuthService::login(...)` | The only endpoint in the system reachable with no `Authorization` header, alongside `refresh`. |
| Refresh | `POST /refresh` | `AuthService::refresh(...)` | Reachable with an expired (but not revoked) access token's associated refresh token. |
| Logout | `POST /logout` | `AuthService::logout(...)` | Requires a valid access token. |
| Logout all | `POST /logout-all` | `AuthService::logoutAll(...)` | Requires a valid access token. |
| Change password | `POST /change-password` | `AuthService::changePassword(...)` | Requires a valid access token; triggers `logoutAll` internally. |

## `UserController` — base path `/api/v1/administration/users`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create user | `POST /` | `createUser(...)` |
| Update user | `PATCH /{id}` | `updateUser(int, ...)` |
| Change status | `POST /{id}/status` | `changeStatus(int, ...)` |
| Get user | `GET /{id}` | `getUser(int)` |
| List users | `GET /?status={status}` | `listUsers(?string)` |

## `RoleController` — base path `/api/v1/administration/roles`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create role | `POST /` | `createRole(...)` |
| Update role | `PATCH /{id}` | `updateRole(int, ...)` |
| Delete role | `DELETE /{id}` | `deleteRole(int)` |
| Get role | `GET /{id}` | `getRole(int)` |
| List roles | `GET /` | `listRoles()` |

## `AuditLogController` — base path `/api/v1/administration/audit-logs`

| Endpoint | Method / URI | Service method |
|---|---|---|
| History for a record | `GET /by-entity/{entityName}/{recordId}` | `getHistoryFor(string, int)` |
| Activity for a user | `GET /by-user/{userId}?from={date}&to={date}` | `getActivityFor(int, ?string, ?string)` |

No `POST`/`PATCH`/`DELETE` endpoints exist on this Controller at all — write access to `audit_logs` happens exclusively through `AuditService::record()`, called from within other modules' Service methods, never from an HTTP request.

## Conclusion

Fully approved, no Open items. This is the minimal Administration slice per the Development Roadmap's Stage 1 — `Configuration`, `Document`, and `ApprovalRequest` Controllers don't exist yet and aren't implied by anything here.
