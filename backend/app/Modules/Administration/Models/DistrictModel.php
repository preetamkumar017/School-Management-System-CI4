<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\District;

class DistrictModel extends BaseModel
{
    protected $table          = 'geo_districts';
    protected $primaryKey     = 'district_id';
    protected $returnType     = District::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'state_id',
        'name',
        'created_by',
        'updated_by',
    ];
}
