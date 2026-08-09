<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

class Appraisal extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'appraisal_id' => 'integer',
            'cycle_id' => 'integer',
            'employee_id' => 'integer',
            'evaluator_id' => 'integer',
            'self_rating' => 'float',
            'evaluator_rating' => 'float',
            'final_rating' => 'float',
        ]);

        parent::__construct($data);
    }
}
