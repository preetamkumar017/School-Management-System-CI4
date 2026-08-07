<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-002.
 *
 * @property int|null $department_id
 * @property string   $department_name
 */
class Department extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'department_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
