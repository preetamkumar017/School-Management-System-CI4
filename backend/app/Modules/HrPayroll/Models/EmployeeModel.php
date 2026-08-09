<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\Employee;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
class EmployeeModel extends BaseModel
{
    protected $table          = 'employees';
    protected $primaryKey     = 'employee_id';
    protected $returnType     = Employee::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'employee_code',
        'full_name',
        'department_id',
        'designation_id',
        'staff_type',
        'cbse_classification',
        'cbse_teacher_code',
        'qualification',
        'experience_years',
        'emergency_contact_name',
        'emergency_contact_phone',
        'documents_json',
        'aadhaar_number',
        'pan_number',
        'pf_uan',
        'esi_number',
        'bank_name',
        'bank_account_number',
        'bank_ifsc_code',
        'joining_date',
        'probation_end_date',
        'confirmation_date',
        'exit_date',
        'salary_structure_json',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodeSalaryStructureJson', 'encodeDocumentsJson'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodeSalaryStructureJson', 'encodeDocumentsJson'];

    /**
     * Same reasoning as GradingSchemeModel::encodeGradeBandJson — the
     * Query Builder binds a raw PHP array as a tuple, not JSON, unless
     * encoded first.
     */
    protected function encodeSalaryStructureJson(array $eventData): array
    {
        if (isset($eventData['data']['salary_structure_json']) && is_array($eventData['data']['salary_structure_json'])) {
            $eventData['data']['salary_structure_json'] = json_encode($eventData['data']['salary_structure_json']);
        }

        return $eventData;
    }

    protected function encodeDocumentsJson(array $eventData): array
    {
        if (isset($eventData['data']['documents_json']) && is_array($eventData['data']['documents_json'])) {
            $eventData['data']['documents_json'] = json_encode($eventData['data']['documents_json']);
        }

        return $eventData;
    }

    public function findByCode(string $value): ?Employee
    {
        return $this->where('employee_code', $value)->first();
    }

    public function existsByCode(string $value): bool
    {
        return $this->where('employee_code', $value)->countAllResults() > 0;
    }

    public function existsByCodeExceptId(string $value, int $id): bool
    {
        return $this->where('employee_code', $value)->where('employee_id !=', $id)->countAllResults() > 0;
    }
}
