<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\Department;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
class DepartmentModel extends BaseModel
{
    protected $table          = 'departments';
    protected $primaryKey     = 'department_id';
    protected $returnType     = Department::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'department_name',
        'created_by',
        'updated_by',
    ];

    public function findByName(string $value): ?Department
    {
        return $this->where('department_name', $value)->first();
    }

    public function existsByName(string $value): bool
    {
        return $this->where('department_name', $value)->countAllResults() > 0;
    }

    public function existsByNameExceptId(string $value, int $id): bool
    {
        return $this->where('department_name', $value)->where('department_id !=', $id)->countAllResults() > 0;
    }
}
