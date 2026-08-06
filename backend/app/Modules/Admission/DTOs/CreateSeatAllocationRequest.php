<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

final class CreateSeatAllocationRequest
{
    public function __construct(
        public readonly int $classId,
        public readonly int $academicSessionId,
        public readonly int $totalCapacity,
        public readonly int $rteQuotaCapacity,
    ) {
    }
}
