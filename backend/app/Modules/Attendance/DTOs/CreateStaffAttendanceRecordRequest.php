<?php

declare(strict_types=1);

namespace App\Modules\Attendance\DTOs;

final class CreateStaffAttendanceRecordRequest
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $attendanceDate,
        public readonly string $state,
    ) {
    }
}
