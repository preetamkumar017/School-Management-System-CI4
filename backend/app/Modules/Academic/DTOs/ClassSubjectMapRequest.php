<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class ClassSubjectMapRequest
{
    public function __construct(
        public readonly int $classId,
        public readonly int $subjectId,
    ) {
    }
}
