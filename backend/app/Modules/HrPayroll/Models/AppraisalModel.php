<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\Appraisal;

class AppraisalModel extends BaseModel
{
    protected $table          = 'appraisals';
    protected $primaryKey     = 'appraisal_id';
    protected $returnType     = Appraisal::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'cycle_id',
        'employee_id',
        'evaluator_id',
        'self_rating',
        'evaluator_rating',
        'final_rating',
        'status',
        'recommendation',
        'evaluator_comments',
    ];
}
