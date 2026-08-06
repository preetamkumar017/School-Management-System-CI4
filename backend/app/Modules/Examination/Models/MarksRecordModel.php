<?php

declare(strict_types=1);

namespace App\Modules\Examination\Models;

use App\Core\BaseModel;
use App\Modules\Examination\Entities\MarksRecord;

/**
 * docs/design/examination/Phase-2-Model-Design.md
 */
class MarksRecordModel extends BaseModel
{
    protected $table          = 'marks_records';
    protected $primaryKey     = 'marks_record_id';
    protected $returnType     = MarksRecord::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'exam_id',
        'student_id',
        'subject_id',
        'marks_obtained',
        'max_marks',
        'is_flagged',
        'is_locked',
        'created_by',
        'updated_by',
    ];

    public function findByExamStudentSubject(int $examId, int $studentId, int $subjectId): ?MarksRecord
    {
        return $this->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->first();
    }

    public function existsByExamStudentSubject(int $examId, int $studentId, int $subjectId): bool
    {
        return $this->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->countAllResults() > 0;
    }

    /**
     * @return list<MarksRecord>
     */
    public function findByExamId(int $examId): array
    {
        return $this->where('exam_id', $examId)->findAll();
    }

    /**
     * Historical locked marks for the same student/subject, across other
     * exams — input to BR-EXM-006 anomaly flagging (ADR-005 §6).
     *
     * @return list<MarksRecord>
     */
    public function findLockedByStudentAndSubjectExceptExam(int $studentId, int $subjectId, int $exceptExamId): array
    {
        return $this->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('exam_id !=', $exceptExamId)
            ->where('is_locked', true)
            ->findAll();
    }

    public function countUnlockedByExamId(int $examId): int
    {
        return $this->where('exam_id', $examId)->where('is_locked', false)->countAllResults();
    }
}
