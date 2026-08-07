<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

final class UpdateDepartmentRequest
{
    public function __construct(
        public readonly string $departmentName,
    ) {
    }
}
