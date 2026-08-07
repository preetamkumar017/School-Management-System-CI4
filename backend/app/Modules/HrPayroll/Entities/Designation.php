<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-003.
 *
 * @property int|null $designation_id
 * @property string   $designation_name
 */
class Designation extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'designation_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
