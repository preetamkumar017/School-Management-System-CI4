<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

class StaffCommunication extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'communication_id' => 'integer',
            'target_audience_id' => 'integer',
            'is_pinned' => 'boolean',
        ]);

        parent::__construct($data);
    }
}
