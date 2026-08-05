---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 — Domain Model
---

# Phase 2 — Admission Model (Repository) Design

Convention: one CI4 Model per entity, acting as its repository (Company Development Standard §3.3). `Application` is classified Transaction data (paginated listings); `SeatAllocation` is classified Master (not paginated).

## `ApplicationModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByReferenceNo(string $value): ?array` | Lookup by business key | Uniqueness (Phase 1) |
| `existsByReferenceNo(string $value): bool` | Create-time uniqueness check (system-generated, but checked before insert in case of a generator collision) | Uniqueness (Phase 1) |
| `findByStatus(string $status, ?int $classId = null): array` | Paginated listing for staff queues (e.g. all `SUBMITTED` applications for a class) | FR-01, FR-03 |
| `find(int $id): ?array` | Plain read | — |
| `existsByAadhaarNumber(string $value): bool` | Duplicate-identity check input to BR-ADM-006 (the Service layer decides what "duplicate" means; this method only answers existence) | BR-ADM-006 |

Already exists per Phase 6 Revision 1 §7 (unaffected by this document): `ApplicationRepository — find/update Application`. This Model is the CI4 realization of that same responsibility.

## `SeatAllocationModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `findByClassAndSession(int $classId, int $academicSessionId): ?array` | The lookup the Service layer uses to resolve which `SeatAllocation` row an `Application` maps to | BR-ADM-001, BR-ADM-003 |
| `existsByClassAndSession(int $classId, int $academicSessionId): bool` / `...ExceptId(...)` | Create/update-time uniqueness checks | Uniqueness (Phase 1) |
| `incrementSeatsFilled(int $id, bool $isRte): bool` | Atomic increment of `seats_filled` (and `rte_seats_filled` if applicable) at confirmation time — the concurrency-safe counter update Phase 1 flagged as a Service-layer locking decision; this method is where that locking is actually applied (e.g. a single atomic `UPDATE ... SET seats_filled = seats_filled + 1 WHERE ... AND seats_filled < total_capacity` guarded by the DB, or an explicit row lock — the exact mechanism is a Phase 4 decision) | BR-ADM-001, BR-ADM-003 |

Already exists per Phase 6 Revision 1 §7 (unaffected by this document): `SeatAllocationRepository — used to perform seat-capacity/RTE re-validation`. This Model, specifically `incrementSeatsFilled`, is the CI4 realization of that responsibility.
