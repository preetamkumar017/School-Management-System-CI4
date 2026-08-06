---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, ADR-006
---

# Phase 3 — Attendance Service and Controller Design

## `AttendanceService`

| Operation | Reason |
|---|---|
| `markAttendance(CreateAttendanceRecordRequest): AttendanceRecordResponse` | Validates `student_id` (SIS), `timetable_entry_id` (Timetable, must be `PUBLISHED`); BR-ATT-001 uniqueness; `marked_by` stamped from `RequestContext::userId()`. |
| `lockAttendance(int $id): AttendanceRecordResponse` | BR-ATT-002. |
| `correctAttendance(int $id, AttendanceCorrectionRequest): AttendanceRecordResponse` | BR-ATT-002/003: same-day edit is direct (no reason required); past the same calendar day as `attendance_date`, `reason` is required (ADR-006 §8), logged via `AuditLog::ACTION_OVERRIDE`. |
| `getAttendanceRecord(int $id): AttendanceRecordResponse` | Plain read. |
| `listByTimetableEntryAndDate(int $timetableEntryId, string $date): array` | The period's roster. |
| `calculateAttendancePercentage(int $studentId, string $fromDate, string $toDate): AttendancePercentageResponse` | FR-13: percentage = (PRESENT + LATE) / total marked records in range, times 100 — days never marked are excluded from the denominator (Phase 1's "flags the gap rather than assuming Present or Absent"), not treated as absent. |
| `isExamEligibilityAtRisk(int $studentId, string $fromDate, string $toDate): bool` | BR-ATT-006 — `calculateAttendancePercentage(...) < 75.0` (ADR-006 §11). **Called by Examination's `MarksRecordService`** (cross-module, the one new edge ADR-006 §11 adds) — this is the seam ADR-005 §2 left open, now closed. |

No delete — "append-mostly" per FR-10's own Security Considerations line.

## `AttendanceController` — base path `/api/v1/attendance/records`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Mark attendance | `POST /` | `markAttendance(...)` |
| Lock | `POST /{id}/lock` | `lockAttendance(int)` |
| Correct | `POST /{id}/correct` | `correctAttendance(int, ...)` |
| Get record | `GET /{id}` | `getAttendanceRecord(int)` |
| List by period/date | `GET /?timetable_entry_id={id}&date={date}` | `listByTimetableEntryAndDate(int, string)` |
| Percentage | `GET /percentage?student_id={id}&from_date={date}&to_date={date}` | `calculateAttendancePercentage(int, string, string)` |

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-006's
resolutions. BR-ATT-004/005/007 are explicitly out of scope, not
silently missing.
