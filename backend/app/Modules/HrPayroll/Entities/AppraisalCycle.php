<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

class AppraisalCycle extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'cycle_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
