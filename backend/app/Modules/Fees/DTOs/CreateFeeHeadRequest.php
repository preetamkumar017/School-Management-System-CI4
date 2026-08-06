<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

final class CreateFeeHeadRequest
{
    public function __construct(
        public readonly string $feeHeadName,
        public readonly bool $isTaxable,
        public readonly ?float $gstRate,
    ) {
    }
}
