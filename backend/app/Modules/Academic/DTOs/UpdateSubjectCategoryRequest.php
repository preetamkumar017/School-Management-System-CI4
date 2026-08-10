<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class UpdateSubjectCategoryRequest
{
    public function __construct(
        public readonly string $categoryName,
        public readonly string $categoryCode,
        public readonly ?string $description = null,
        public readonly int $isActive = 1,
    ) {
    }
}
