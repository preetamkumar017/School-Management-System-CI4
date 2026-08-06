<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

final class CreateExamRequest
{
    public function __construct(
        public readonly string $examName,
        public readonly int $classId,
        public readonly int $academicSessionId,
        public readonly int $gradingSchemeId,
        public readonly string $examDate,
    ) {
    }
}
