<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\StaffCommunication;

class StaffCommunicationModel extends BaseModel
{
    protected $table          = 'staff_communications';
    protected $primaryKey     = 'communication_id';
    protected $returnType     = StaffCommunication::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'type',
        'title',
        'message',
        'target_audience',
        'target_audience_id',
        'publish_date',
        'expiry_date',
        'is_pinned',
        'status',
        'created_by',
        'updated_by',
    ];
}
