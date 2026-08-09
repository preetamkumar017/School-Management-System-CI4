<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\AppraisalKpi;

class AppraisalKpiModel extends BaseModel
{
    protected $table          = 'appraisal_kpis';
    protected $primaryKey     = 'kpi_id';
    protected $returnType     = AppraisalKpi::class;
    protected $useSoftDeletes = false; // no deleted_at

    protected $allowedFields = [
        'appraisal_id',
        'kpi_name',
        'weightage',
        'self_score',
        'evaluator_score',
        'self_comments',
    ];
}
