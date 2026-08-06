<?php

declare(strict_types=1);

namespace App\Modules\Timetable\DTOs;

final class CreateTimetableEntryRequest
{
    public function __construct(
        public readonly int $sectionId,
        public readonly int $subjectId,
        public readonly int $employeeId,
        public readonly string $dayOfWeek,
        public readonly int $periodNo,
        public readonly ?string $roomId,
    ) {
    }
}
