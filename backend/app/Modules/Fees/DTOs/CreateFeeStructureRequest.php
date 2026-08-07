<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

final class CreateFeeStructureRequest
{
    public function __construct(
        public readonly int $classId,
        public readonly int $feeHeadId,
        public readonly int $academicSessionId,
        public readonly string $category,
        public readonly float $amount,
        public readonly ?int $routeId = null,
    ) {
    }
}
