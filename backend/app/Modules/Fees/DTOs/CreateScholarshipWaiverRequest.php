<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

final class CreateScholarshipWaiverRequest
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $feeHeadId,
        public readonly string $waiverType,
        public readonly float $waiverAmount,
    ) {
    }
}
