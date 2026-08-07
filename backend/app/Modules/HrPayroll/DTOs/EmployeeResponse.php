<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

use App\Modules\HrPayroll\Entities\Employee;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
final class EmployeeResponse
{
    public readonly int $employeeId;
    public readonly string $employeeCode;
    public readonly string $fullName;
    public readonly int $departmentId;
    public readonly int $designationId;
    public readonly string $joiningDate;
    public readonly ?string $exitDate;

    /** @var array<string, mixed> */
    public readonly array $salaryStructureJson;
    public readonly string $status;

    public function __construct(Employee $employee)
    {
        $this->employeeId          = $employee->employee_id;
        $this->employeeCode        = $employee->employee_code;
        $this->fullName            = $employee->full_name;
        $this->departmentId        = $employee->department_id;
        $this->designationId       = $employee->designation_id;
        $this->joiningDate         = $employee->joining_date;
        $this->exitDate            = $employee->exit_date;
        $this->salaryStructureJson = $employee->salary_structure_json;
        $this->status              = $employee->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id'            => $this->employeeId,
            'employee_code'          => $this->employeeCode,
            'full_name'              => $this->fullName,
            'department_id'          => $this->departmentId,
            'designation_id'         => $this->designationId,
            'joining_date'           => $this->joiningDate,
            'exit_date'              => $this->exitDate,
            'salary_structure_json'  => $this->salaryStructureJson,
            'status'                 => $this->status,
        ];
    }
}
