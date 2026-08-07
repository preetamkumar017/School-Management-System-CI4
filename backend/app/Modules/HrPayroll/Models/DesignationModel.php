<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\Designation;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
class DesignationModel extends BaseModel
{
    protected $table          = 'designations';
    protected $primaryKey     = 'designation_id';
    protected $returnType     = Designation::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'designation_name',
        'created_by',
        'updated_by',
    ];

    public function findByName(string $value): ?Designation
    {
        return $this->where('designation_name', $value)->first();
    }

    public function existsByName(string $value): bool
    {
        return $this->where('designation_name', $value)->countAllResults() > 0;
    }

    public function existsByNameExceptId(string $value, int $id): bool
    {
        return $this->where('designation_name', $value)->where('designation_id !=', $id)->countAllResults() > 0;
    }
}
