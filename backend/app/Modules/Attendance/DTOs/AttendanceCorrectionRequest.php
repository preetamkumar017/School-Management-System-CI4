<?php

declare(strict_types=1);

namespace App\Modules\Attendance\DTOs;

/**
 * docs/ADR/ADR-006-timetable-and-attendance-scope-decisions.md §8 — same
 * shape as Examination's MarksRecordReevaluateRequest (ADR-005 §7).
 * reason is required only once the caller is past the same-day edit
 * window; the Controller/Service decide that, not this DTO.
 */
final class AttendanceCorrectionRequest
{
    public function __construct(
        public readonly string $state,
        public readonly ?string $reason,
    ) {
    }
}
