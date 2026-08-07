---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, ADR-010
---

# Phase 2 — Communication Model and DTO Design

Convention: both `Circular` and `NotificationLog` are Transaction data,
exposed via scoped listings (`Circular` by `target_audience`,
`NotificationLog` by recipient), matching every prior module's
precedent — no bulk paginated index.

## `CircularModel`

| Method | Purpose |
|---|---|
| `findByTargetAudience(string $targetAudience): array` | Portal-board query — everything posted for a given audience |

## `NotificationLogModel`

| Method | Purpose |
|---|---|
| `findByRecipient(string $recipientType, int $recipientRefId): array` | A recipient's notification history |

## DTOs

`CreateCircularRequest`: `author_id`, `post_type`, `title`, `body`,
`target_audience`. `CircularResponse`: `circular_id`, `author_id`,
`post_type`, `title`, `body`, `target_audience`, `posted_at`, `status`.

`CreateNotificationLogRequest`: `recipient_type`, `recipient_ref_id`,
`channel`, `trigger_event`. `MarkNotificationFailedRequest`:
`failure_reason` (required). `NotificationLogResponse`:
`notification_log_id`, `recipient_type`, `recipient_ref_id`, `channel`,
`trigger_event`, `status`, `dispatched_at`, `failure_reason`.
