<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

final class CreatePayrollRunRequest
{
    /**
     * @param array<string, mixed> $deductionsJson
     */
    public function __construct(
        public readonly int $employeeId,
        public readonly string $payPeriod,
        public readonly float $grossPay,
        public readonly array $deductionsJson,
    ) {
    }
}
