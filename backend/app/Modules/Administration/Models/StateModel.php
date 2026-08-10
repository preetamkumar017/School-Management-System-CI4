<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\State;

class StateModel extends BaseModel
{
    protected $table          = 'geo_states';
    protected $primaryKey     = 'state_id';
    protected $returnType     = State::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'name',
        'code',
        'created_by',
        'updated_by',
    ];
}
