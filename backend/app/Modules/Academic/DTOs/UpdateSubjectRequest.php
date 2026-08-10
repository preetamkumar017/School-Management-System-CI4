<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class UpdateSubjectRequest
{
    public function __construct(
        public readonly string $subjectName,
        public readonly string $subjectCode,
        public readonly ?int $subjectCategoryId = null,
        public readonly int $isLanguageSubject = 0,
        public readonly string $streamApplicability = 'ALL',
    ) {
    }
}
