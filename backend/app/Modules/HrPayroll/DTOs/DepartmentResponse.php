<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

use App\Modules\HrPayroll\Entities\Department;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
final class DepartmentResponse
{
    public readonly int $departmentId;
    public readonly string $departmentName;

    public function __construct(Department $department)
    {
        $this->departmentId   = $department->department_id;
        $this->departmentName = $department->department_name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'department_id'   => $this->departmentId,
            'department_name' => $this->departmentName,
        ];
    }
}
