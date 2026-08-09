<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Core\BaseModel;
use App\Modules\Attendance\Entities\StaffPunch;

class StaffPunchModel extends BaseModel
{
    protected $table          = 'staff_punches';
    protected $primaryKey     = 'punch_id';
    protected $returnType     = StaffPunch::class;
    protected $useTimestamps  = true; // only created_at needed, BaseModel handles it

    protected $allowedFields = [
        'employee_id',
        'punch_time',
        'punch_type',
        'source',
        'device_id',
        'created_by', // Allowed explicitly if needed, though BaseModel overrides insert
    ];
}
