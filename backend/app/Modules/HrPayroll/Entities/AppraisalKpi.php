<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

class AppraisalKpi extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'kpi_id' => 'integer',
            'appraisal_id' => 'integer',
            'weightage' => 'integer',
            'self_score' => 'float',
            'evaluator_score' => 'float',
        ]);

        parent::__construct($data);
    }
}
