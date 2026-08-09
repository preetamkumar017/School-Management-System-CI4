<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;

class StaffCommunicationReadModel extends BaseModel
{
    protected $table          = 'staff_communication_reads';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'communication_id',
        'user_id',
        'read_at',
    ];
}
