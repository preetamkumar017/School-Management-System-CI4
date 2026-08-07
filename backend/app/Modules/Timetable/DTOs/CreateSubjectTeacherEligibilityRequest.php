<?php

declare(strict_types=1);

namespace App\Modules\Timetable\DTOs;

final class CreateSubjectTeacherEligibilityRequest
{
    public function __construct(
        public readonly int $employeeId,
        public readonly int $subjectId,
    ) {
    }
}
