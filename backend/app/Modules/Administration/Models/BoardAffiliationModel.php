<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\BoardAffiliation;

class BoardAffiliationModel extends BaseModel
{
    protected $table          = 'board_affiliations';
    protected $primaryKey     = 'affiliation_id';
    protected $returnType     = BoardAffiliation::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'board_id',
        'academic_session_id',
        'affiliation_number',
        'validity_start',
        'validity_end',
        'status',
        'created_by',
        'updated_by',
    ];
}
