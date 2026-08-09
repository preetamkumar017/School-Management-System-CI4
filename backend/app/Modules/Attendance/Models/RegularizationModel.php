<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Core\BaseModel;
use App\Modules\Attendance\Entities\Regularization;

class RegularizationModel extends BaseModel
{
    protected $table          = 'staff_attendance_regularizations';
    protected $primaryKey     = 'regularization_id';
    protected $returnType     = Regularization::class;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'staff_attendance_id',
        'requested_state',
        'reason',
        'status',
        'approver_id',
        'approved_at',
    ];
}
