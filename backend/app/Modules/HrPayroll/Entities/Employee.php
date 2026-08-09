<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-001.
 *
 * @property int|null            $employee_id
 * @property string              $employee_code
 * @property string              $full_name
 * @property int                 $department_id
 * @property int                 $designation_id
 * @property string              $staff_type
 * @property string|null         $cbse_classification
 * @property string|null         $cbse_teacher_code
 * @property string|null         $qualification
 * @property float|null          $experience_years
 * @property string|null         $emergency_contact_name
 * @property string|null         $emergency_contact_phone
 * @property array<string,mixed> $documents_json
 * @property string|null         $aadhaar_number
 * @property string|null         $pan_number
 * @property string|null         $pf_uan
 * @property string|null         $esi_number
 * @property string|null         $bank_name
 * @property string|null         $bank_account_number
 * @property string|null         $bank_ifsc_code
 * @property string              $joining_date
 * @property string|null         $probation_end_date
 * @property string|null         $confirmation_date
 * @property string|null         $exit_date
 * @property array<string,mixed> $salary_structure_json
 * @property string              $status
 */
class Employee extends BaseEntity
{
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_EXITED = 'Exited';

    public const STAFF_TYPE_TEACHING       = 'Teaching';
    public const STAFF_TYPE_NON_TEACHING   = 'NonTeaching';
    public const STAFF_TYPE_SUPPORT        = 'Support';
    public const STAFF_TYPE_ADMINISTRATIVE = 'Administrative';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'employee_id'           => 'integer',
            'department_id'         => 'integer',
            'designation_id'        => 'integer',
            'salary_structure_json' => 'json-array',
            'documents_json'        => 'json-array',
            'experience_years'      => 'float',
        ]);

        parent::__construct($data);
    }
}
