<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class CreateClassBoardFrameworkMapRequest
{
    public function __construct(
        public readonly int $academicSessionId,
        public readonly int $classId,
        public readonly int $frameworkId,
    ) {
    }
}
