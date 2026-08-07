<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

final class CreateLeaveRequestRequest
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $leaveType,
        public readonly string $startDate,
        public readonly string $endDate,
    ) {
    }
}
