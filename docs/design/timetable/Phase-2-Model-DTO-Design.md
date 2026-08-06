---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, ADR-006
---

# Phase 2 — Timetable Model and DTO Design

## `TimetableEntryModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `existsByTeacherDayPeriod(int $employeeId, string $dayOfWeek, int $periodNo): bool` / `...ExceptId(...)` | Create/update-time BR-TT-001 check (also DB-enforced; Service checks first for a clean error message) | BR-TT-001 |
| `existsBySectionDayPeriod(int $sectionId, string $dayOfWeek, int $periodNo): bool` / `...ExceptId(...)` | Create/update-time uniqueness | Uniqueness (Phase 1) |
| `existsByRoomDayPeriod(string $roomId, string $dayOfWeek, int $periodNo): bool` / `...ExceptId(...)` | BR-TT-002 room double-booking (Service-layer only check, ADR-006 §5) | BR-TT-002 |
| `countByEmployeeId(int $employeeId): int` | BR-TT-006 weekly load ceiling input | BR-TT-006 |
| `findBySectionId(int $sectionId): array` | Section's weekly schedule | FR-14 |
| `findLatestVersion(int $sectionId, string $dayOfWeek, int $periodNo): ?TimetableEntry` | Resolves the current entry for a slot before a revision (BR-TT-005) | BR-TT-005 |

## DTOs

`CreateTimetableEntryRequest`: `section_id`, `subject_id`, `employee_id`,
`day_of_week`, `period_no`, `room_id` (optional) — all required except
`room_id`.

`TimetableEntryResponse`: `timetable_entry_id`, `section_id`, `subject_id`,
`employee_id`, `day_of_week`, `period_no`, `room_id`, `version_no`,
`status`.

No `Update…Request` distinct from Create — BR-TT-005 means "update" is
never an in-place field edit; it is always `reviseEntry`, which takes the
same shape as `CreateTimetableEntryRequest`.
