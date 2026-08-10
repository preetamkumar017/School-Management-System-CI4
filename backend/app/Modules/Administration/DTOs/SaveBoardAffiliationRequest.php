<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

class SaveBoardAffiliationRequest
{
    public function __construct(
        public readonly int $boardId,
        public readonly int $academicSessionId,
        public readonly string $affiliationNumber,
        public readonly ?string $validityStart = null,
        public readonly ?string $validityEnd = null,
        public readonly string $status = 'ACTIVE'
    ) {}
}
