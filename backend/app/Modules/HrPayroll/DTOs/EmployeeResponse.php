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
    public readonly string $staffType;
    public readonly ?string $qualification;
    public readonly ?string $aadhaarNumber;
    public readonly ?string $panNumber;
    public readonly ?string $pfUan;
    public readonly ?string $esiNumber;
    public readonly ?string $bankName;
    public readonly ?string $bankAccountNumber;
    public readonly ?string $bankIfscCode;
    public readonly string $joiningDate;
    public readonly ?string $probationEndDate;
    public readonly ?string $confirmationDate;
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
        $this->staffType           = $employee->staff_type ?? Employee::STAFF_TYPE_TEACHING;
        $this->qualification       = $employee->qualification;
        $this->aadhaarNumber       = $employee->aadhaar_number;
        $this->panNumber           = $employee->pan_number;
        $this->pfUan               = $employee->pf_uan;
        $this->esiNumber           = $employee->esi_number;
        $this->bankName            = $employee->bank_name;
        $this->bankAccountNumber   = $employee->bank_account_number;
        $this->bankIfscCode        = $employee->bank_ifsc_code;
        $this->joiningDate         = $employee->joining_date;
        $this->probationEndDate    = $employee->probation_end_date;
        $this->confirmationDate    = $employee->confirmation_date;
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
            'staff_type'             => $this->staffType,
            'qualification'          => $this->qualification,
            'aadhaar_number'         => $this->aadhaarNumber,
            'pan_number'             => $this->panNumber,
            'pf_uan'                 => $this->pfUan,
            'esi_number'             => $this->esiNumber,
            'bank_name'              => $this->bankName,
            'bank_account_number'    => $this->bankAccountNumber,
            'bank_ifsc_code'         => $this->bankIfscCode,
            'joining_date'           => $this->joiningDate,
            'probation_end_date'     => $this->probationEndDate,
            'confirmation_date'      => $this->confirmationDate,
            'exit_date'              => $this->exitDate,
            'salary_structure_json'  => $this->salaryStructureJson,
            'status'                 => $this->status,
        ];
    }
}
