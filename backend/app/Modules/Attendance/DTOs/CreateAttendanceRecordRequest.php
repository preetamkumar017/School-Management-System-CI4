<?php

declare(strict_types=1);

namespace App\Modules\Attendance\DTOs;

final class CreateAttendanceRecordRequest
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $timetableEntryId,
        public readonly string $attendanceDate,
        public readonly string $state,
    ) {
    }
}
