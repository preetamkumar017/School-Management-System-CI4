<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

final class DecideLeaveRequestRequest
{
    public function __construct(
        public readonly string $decision,
        public readonly ?string $overrideReason,
    ) {
    }
}
