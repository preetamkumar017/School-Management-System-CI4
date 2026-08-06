<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

use App\Modules\Examination\Entities\MarksRecord;

/**
 * docs/design/examination/Phase-3-DTO-Design.md
 */
final class MarksRecordResponse
{
    public readonly int $marksRecordId;
    public readonly int $examId;
    public readonly int $studentId;
    public readonly int $subjectId;
    public readonly ?float $marksObtained;
    public readonly float $maxMarks;
    public readonly bool $isFlagged;
    public readonly bool $isLocked;

    public function __construct(MarksRecord $marksRecord)
    {
        $this->marksRecordId = $marksRecord->marks_record_id;
        $this->examId        = $marksRecord->exam_id;
        $this->studentId     = $marksRecord->student_id;
        $this->subjectId     = $marksRecord->subject_id;
        $this->marksObtained = $marksRecord->marks_obtained;
        $this->maxMarks      = $marksRecord->max_marks;
        $this->isFlagged     = $marksRecord->is_flagged;
        $this->isLocked      = $marksRecord->is_locked;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'marks_record_id' => $this->marksRecordId,
            'exam_id'         => $this->examId,
            'student_id'      => $this->studentId,
            'subject_id'      => $this->subjectId,
            'marks_obtained'  => $this->marksObtained,
            'max_marks'       => $this->maxMarks,
            'is_flagged'      => $this->isFlagged,
            'is_locked'       => $this->isLocked,
        ];
    }
}
