<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

use App\Modules\Examination\Entities\Exam;

/**
 * docs/design/examination/Phase-3-DTO-Design.md
 */
final class ExamResponse
{
    public readonly int $examId;
    public readonly string $examName;
    public readonly int $classId;
    public readonly int $academicSessionId;
    public readonly int $gradingSchemeId;
    public readonly string $examDate;
    public readonly string $status;

    public function __construct(Exam $exam)
    {
        $this->examId            = $exam->exam_id;
        $this->examName          = $exam->exam_name;
        $this->classId           = $exam->class_id;
        $this->academicSessionId = $exam->academic_session_id;
        $this->gradingSchemeId   = $exam->grading_scheme_id;
        $this->examDate          = (string) $exam->exam_date;
        $this->status            = $exam->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exam_id'             => $this->examId,
            'exam_name'           => $this->examName,
            'class_id'            => $this->classId,
            'academic_session_id' => $this->academicSessionId,
            'grading_scheme_id'   => $this->gradingSchemeId,
            'exam_date'           => $this->examDate,
            'status'              => $this->status,
        ];
    }
}
