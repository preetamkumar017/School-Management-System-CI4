---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, ADR-006
---

# Phase 2 — Attendance Model and DTO Design

## `AttendanceRecordModel`

| Method | Purpose | BR/FR basis |
|---|---|---|
| `existsByStudentEntryDate(int $studentId, int $timetableEntryId, string $date): bool` | Create-time BR-ATT-001 check (also DB-enforced) | BR-ATT-001 |
| `findByStudentEntryDate(int $studentId, int $timetableEntryId, string $date): ?AttendanceRecord` | Lookup by business key | BR-ATT-001 |
| `findByStudentBetween(int $studentId, string $fromDate, string $toDate): array` | Input to BR-ATT-006 percentage calculation | FR-13, BR-ATT-006 |
| `countByStudentBetween(int $studentId, string $fromDate, string $toDate): int` / `countByStudentAndStateBetween(int, string, string, string): int` | Percentage numerator/denominator | FR-13 |
| `findByTimetableEntryAndDate(int $timetableEntryId, string $date): array` | The period's marked roster | FR-10 |

## DTOs

`CreateAttendanceRecordRequest`: `student_id`, `timetable_entry_id`,
`attendance_date`, `state`.

`AttendanceCorrectionRequest`: `state`, `reason` (required — same shape as
Examination's `MarksRecordReevaluateRequest`, ADR-005 §7 precedent).

`AttendanceRecordResponse`: `attendance_record_id`, `student_id`,
`timetable_entry_id`, `attendance_date`, `state`, `marked_by`, `is_locked`.

`AttendancePercentageResponse` (read-model, not a persisted entity):
`student_id`, `from_date`, `to_date`, `percentage`, `is_exam_eligibility_at_risk`
(BR-ATT-006, threshold 75%, ADR-006 §11).
