---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, Phase 2, ADR-010
---

# Phase 3 — Communication Service and Controller Design

## `CircularService`

| Operation | Reason |
|---|---|
| `createCircular(CreateCircularRequest): CircularResponse` | Validates `author_id` (Administration `UserService`); `status = Posted`. |
| `retract(int $id): CircularResponse` | Rejects if already `Retracted`; sets `status = Retracted`. |
| `getCircular(int $id): CircularResponse` | Plain read. |
| `listByTargetAudience(string $targetAudience): array` | Portal-board query. |

## `NotificationLogService`

| Operation | Reason |
|---|---|
| `create(CreateNotificationLogRequest): NotificationLogResponse` | Validates `recipient_ref_id` via SIS `GuardianService`, HR & Payroll `EmployeeService`, or Administration `UserService`, dispatched on `recipient_type`; `status = Queued`. Called both directly (a future Controller endpoint) and internally by other modules closing a notification seam (ADR-010 §3). |
| `markDispatched(int $id): NotificationLogResponse` | `status → Dispatched`, `dispatched_at = now`. |
| `markDelivered(int $id): NotificationLogResponse` | `status → Delivered`. |
| `markFailed(int $id, MarkNotificationFailedRequest): NotificationLogResponse` | BR-COM-004: `status → Failed`, `failure_reason` stored, logged via `AuditLog::ACTION_OVERRIDE`. |
| `getNotificationLog(int $id): NotificationLogResponse` | Plain read. |
| `listByRecipient(string $recipientType, int $recipientRefId): array` | A recipient's notification history. |

## Controllers — base path `/api/v1/communication/...`

| Controller | Base path | Endpoints |
|---|---|---|
| `CircularController` | `/circulars` | `POST /`, `POST /{id}/retract`, `GET /{id}`, `GET /?target_audience` |
| `NotificationLogController` | `/notification-logs` | `POST /`, `POST /{id}/dispatch`, `POST /{id}/deliver`, `POST /{id}/fail`, `GET /{id}`, `GET /?recipient_type&recipient_ref_id` |

## Cross-module integration (ADR-010 §3)

`App\Modules\Timetable\Services\TimetableEntryService::reviseEntry`
(BR-TT-005) and `App\Modules\Attendance\Services\AttendanceService::
markAttendance` when `state = ABSENT` (BR-ATT-004) each call
`Config\Services::notificationLogService()->create()` to log a `Queued`
notification — the logging half of both seams; actual dispatch remains
out of scope (ADR-010 §1, §2).

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-010's
resolutions. BR-COM-001/002/003 and live gateway dispatch are explicitly
out of scope, not silently missing.
