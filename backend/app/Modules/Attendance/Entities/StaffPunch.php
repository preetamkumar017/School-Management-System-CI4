<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Entities;

use CodeIgniter\Entity\Entity;

class StaffPunch extends Entity
{
    protected $casts = [
        'punch_id'    => 'integer',
        'employee_id' => 'integer',
        'created_by'  => 'integer',
    ];
}
