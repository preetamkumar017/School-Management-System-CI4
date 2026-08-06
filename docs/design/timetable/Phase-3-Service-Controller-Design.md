---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1, Phase 2, ADR-006
---

# Phase 3 — Timetable Service and Controller Design

## `TimetableEntryService`

| Operation | Reason |
|---|---|
| `createEntry(CreateTimetableEntryRequest): TimetableEntryResponse` | Validates `section_id`/`subject_id` (cross-module, Academic); BR-TT-001 (teacher), BR-TT-002 (section + room, if `room_id` given), BR-TT-006 (weekly load ceiling, 30/week default, ADR-006 §7); status `DRAFT`. |
| `publishEntry(int $id): TimetableEntryResponse` | `DRAFT → PUBLISHED`. |
| `reviseEntry(int $id, CreateTimetableEntryRequest): TimetableEntryResponse` | BR-TT-005: only callable on a `PUBLISHED` entry (a `DRAFT` entry is edited via a plain update — not modeled as a separate operation since Phase 2 has no distinct Update DTO; **decided**: revision mutates the row in place and increments `version_no`, rather than inserting a parallel history row — no versions table exists anywhere in this codebase's design, and `AuditLog` already captures the full before/after value on every revision, satisfying the auditability half of BR-TT-005 without a second storage mechanism). Re-validates BR-TT-001/002/006 against the new slot. |
| `getEntry(int $id): TimetableEntryResponse` | Plain read. |
| `listEntriesBySection(int $sectionId): array` | A section's weekly schedule. |

No delete — `TimetableEntry` is Reference data with "Archive at term end,"
same soft-delete-only reasoning as every other module's Master/Reference
entities.

## `TimetableEntryController` — base path `/api/v1/timetable/entries`

| Endpoint | Method / URI | Service method |
|---|---|---|
| Create entry | `POST /` | `createEntry(...)` |
| Publish | `POST /{id}/publish` | `publishEntry(int)` |
| Revise | `POST /{id}/revise` | `reviseEntry(int, ...)` |
| Get entry | `GET /{id}` | `getEntry(int)` |
| List by section | `GET /?section_id={sectionId}` | `listEntriesBySection(int)` |

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-006's
resolutions. BR-TT-003/004 and any Substitution/notification behavior
are explicitly out of scope, not silently missing.
