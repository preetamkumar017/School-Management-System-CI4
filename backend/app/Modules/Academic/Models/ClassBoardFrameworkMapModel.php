<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\ClassBoardFrameworkMap;

class ClassBoardFrameworkMapModel extends BaseModel
{
    protected $table          = 'class_board_framework_map';
    protected $primaryKey     = 'class_board_map_id';
    protected $returnType     = ClassBoardFrameworkMap::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'academic_session_id',
        'class_id',
        'framework_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function exists(int $sessionId, int $classId): bool
    {
        return $this->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->countAllResults() > 0;
    }

    public function existsExceptId(int $sessionId, int $classId, int $id): bool
    {
        return $this->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('class_board_map_id !=', $id)
            ->countAllResults() > 0;
    }
}
