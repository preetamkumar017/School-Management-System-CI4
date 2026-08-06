<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

final class CreatePromotionRecordRequest
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $fromSessionId,
        public readonly int $toSessionId,
        public readonly int $fromClassId,
        public readonly int $toClassId,
        public readonly bool $feeClosureConfirmed,
    ) {
    }
}
