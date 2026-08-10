<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\Block;

class BlockModel extends BaseModel
{
    protected $table          = 'geo_blocks';
    protected $primaryKey     = 'block_id';
    protected $returnType     = Block::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'district_id',
        'name',
        'created_by',
        'updated_by',
    ];
}
