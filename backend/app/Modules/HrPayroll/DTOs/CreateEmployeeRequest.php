<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

final class CreateEmployeeRequest
{
    /**
     * @param array<string, mixed> $salaryStructureJson
     */
    public function __construct(
        public readonly string $employeeCode,
        public readonly string $fullName,
        public readonly int $departmentId,
        public readonly int $designationId,
        public readonly string $joiningDate,
        public readonly array $salaryStructureJson,
    ) {
    }
}
