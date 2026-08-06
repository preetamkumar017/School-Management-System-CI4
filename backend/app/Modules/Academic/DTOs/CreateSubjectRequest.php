<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class CreateSubjectRequest
{
    public function __construct(
        public readonly string $subjectName,
        public readonly string $subjectCode,
    ) {
    }
}
