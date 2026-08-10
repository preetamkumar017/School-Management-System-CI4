<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class CreateSubjectCategoryRequest
{
    public function __construct(
        public readonly string $categoryName,
        public readonly string $categoryCode,
        public readonly ?string $description = null,
    ) {
    }
}
