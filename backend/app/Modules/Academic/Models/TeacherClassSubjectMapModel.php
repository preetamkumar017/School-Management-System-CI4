<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\TeacherClassSubjectMap;

class TeacherClassSubjectMapModel extends BaseModel
{
    protected $table          = 'teacher_class_subject_map';
    protected $primaryKey     = 'teacher_class_subject_map_id';
    protected $returnType     = TeacherClassSubjectMap::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'academic_session_id',
        'class_id',
        'section_id',
        'subject_id',
        'employee_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function exists(int $sessionId, int $sectionId, int $subjectId): bool
    {
        return $this->where('academic_session_id', $sessionId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->countAllResults() > 0;
    }

    public function existsExceptId(int $sessionId, int $sectionId, int $subjectId, int $id): bool
    {
        return $this->where('academic_session_id', $sessionId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('teacher_class_subject_map_id !=', $id)
            ->countAllResults() > 0;
    }
}
