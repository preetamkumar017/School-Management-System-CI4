<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Entities;

use App\Core\BaseEntity;

class Regularization extends BaseEntity
{
    protected $casts = [
        'regularization_id'   => 'integer',
        'staff_attendance_id' => 'integer',
        'approver_id'         => 'integer',
        'created_by'          => 'integer',
        'updated_by'          => 'integer',
    ];
}
