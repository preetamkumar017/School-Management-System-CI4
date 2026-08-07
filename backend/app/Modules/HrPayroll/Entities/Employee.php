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
 * @property string              $joining_date
 * @property string|null         $exit_date
 * @property array<string,mixed> $salary_structure_json
 * @property string              $status
 */
class Employee extends BaseEntity
{
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_EXITED = 'Exited';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'employee_id'            => 'integer',
            'department_id'          => 'integer',
            'designation_id'         => 'integer',
            'salary_structure_json'  => 'json-array',
        ]);

        parent::__construct($data);
    }
}
