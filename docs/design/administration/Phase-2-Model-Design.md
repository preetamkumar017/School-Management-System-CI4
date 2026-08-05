---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 — Domain Model
---

# Phase 2 — Administration Model (Repository) Design

## `UserModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByUsername(string $value): ?array` | Login lookup | Auth |
| `existsByUsername(string $value): bool` / `...ExceptId(...)` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `incrementFailedLoginCount(int $id): int` | Atomic increment, returns new count | Company Development Standard §9 (lockout at 5) |
| `resetFailedLoginCount(int $id): bool` | Called on successful login | §9 |
| `findByOwner(string $ownerType, int $ownerRefId): ?array` | Resolves the `User` row for a given polymorphic owner (e.g. does this `Student` already have a login) | Phase 1 |

## `RoleModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByRoleName(string $value): ?array` | Lookup by business key | Uniqueness (Phase 1) |
| `existsByRoleName(string $value): bool` / `...ExceptId(...)` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `isReferencedByAnyUser(int $roleId): bool` | Input to the Service layer's delete-protection check (RESTRICT) | Phase 1 |
| `findAll(): array` | Master-data listing, no pagination | — |

## `AuditLogModel`

Deliberately minimal — write path only, no update/delete methods exist on this Model at all (Phase 1's write-once rule is enforced by omission, not by a guard clause).

| Method | Purpose | BR/FR basis |
|---|---|---|
| `insert(array $data): int` | The only write method — CI4's base Model `insert()`, not overridden with any update/delete capability | Phase 1 |
| `findByEntity(string $entityName, int $recordId): array` | All audit rows for a given record — the consolidated audit-query capability every module's "view history" screen calls through this Service | Appendix-F NFR-LOG-010 |
| `findByPerformedBy(int $userId, ?string $fromDate = null, ?string $toDate = null): array` | Paginated (Transaction-classified data) — activity listing for a given user | — |

## `RefreshTokenModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findValidByTokenHash(string $tokenHash): ?array` | Refresh-flow lookup — excludes expired/revoked rows | Company Development Standard §9 |
| `revokeAllForUser(int $userId): bool` | Called on logout-all-sessions, password change, or access revocation | §9 ("password change revokes all active sessions") |
| `revokeByTokenHash(string $tokenHash): bool` | Single-session logout | §9 |
