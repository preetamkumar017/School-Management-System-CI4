<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\Board;

class BoardModel extends BaseModel
{
    protected $table          = 'geo_boards';
    protected $primaryKey     = 'board_id';
    protected $returnType     = Board::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'name',
        'short_name',
        'board_type',
        'country',
        'state_applicability',
        'status',
        'description',
        'created_by',
        'updated_by',
    ];
}
