<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\AppraisalCycle;

class AppraisalCycleModel extends BaseModel
{
    protected $table          = 'appraisal_cycles';
    protected $primaryKey     = 'cycle_id';
    protected $returnType     = AppraisalCycle::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'name',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'updated_by',
    ];
}
