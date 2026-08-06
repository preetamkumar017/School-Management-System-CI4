<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

final class CreateMarksRecordRequest
{
    public function __construct(
        public readonly int $examId,
        public readonly int $studentId,
        public readonly int $subjectId,
        public readonly ?float $marksObtained,
        public readonly float $maxMarks,
    ) {
    }
}
