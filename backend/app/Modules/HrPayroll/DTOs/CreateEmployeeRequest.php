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
        public readonly string $staffType = 'Teaching',
        public readonly ?string $cbseClassification = 'None',
        public readonly ?string $cbseTeacherCode = null,
        public readonly ?string $qualification = null,
        public readonly ?string $aadhaarNumber = null,
        public readonly ?string $panNumber = null,
        public readonly ?string $pfUan = null,
        public readonly ?string $esiNumber = null,
        public readonly ?string $bankName = null,
        public readonly ?string $bankAccountNumber = null,
        public readonly ?string $bankIfscCode = null,
        public readonly ?string $probationEndDate = null,
        public readonly ?string $confirmationDate = null,
    ) {
    }
}
