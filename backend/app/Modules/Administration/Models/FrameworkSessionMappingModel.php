<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\FrameworkSessionMapping;

class FrameworkSessionMappingModel extends BaseModel
{
    protected $table          = 'framework_session_mappings';
    protected $primaryKey     = 'mapping_id';
    protected $returnType     = FrameworkSessionMapping::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'framework_id',
        'academic_session_id',
        'created_by',
        'updated_by',
    ];
}
