<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class CreateSectionRequest
{
    public function __construct(
        public readonly int $classId,
        public readonly string $sectionName,
        public readonly int $capacity,
    ) {
    }
}
