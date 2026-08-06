<?php

declare(strict_types=1);

namespace App\Modules\Examination\Models;

use App\Core\BaseModel;
use App\Modules\Examination\Entities\Exam;

/**
 * docs/design/examination/Phase-2-Model-Design.md
 */
class ExamModel extends BaseModel
{
    protected $table          = 'exams';
    protected $primaryKey     = 'exam_id';
    protected $returnType     = Exam::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'exam_name',
        'class_id',
        'academic_session_id',
        'grading_scheme_id',
        'exam_date',
        'status',
        'created_by',
        'updated_by',
    ];

    public function existsByClassExamNameSession(int $classId, string $examName, int $academicSessionId): bool
    {
        return $this->where('class_id', $classId)
            ->where('exam_name', $examName)
            ->where('academic_session_id', $academicSessionId)
            ->countAllResults() > 0;
    }

    /**
     * @return list<Exam>
     */
    public function findByClassAndSession(int $classId, int $academicSessionId): array
    {
        return $this->where('class_id', $classId)->where('academic_session_id', $academicSessionId)->findAll();
    }
}
