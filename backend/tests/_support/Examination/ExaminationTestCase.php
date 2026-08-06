<?php

declare(strict_types=1);

namespace Tests\Support\Examination;

use App\Modules\Examination\Models\ExamModel;
use App\Modules\Examination\Models\MarksRecordModel;
use Tests\Support\Sis\SisTestCase;

/**
 * @internal
 * Reuses SisTestCase's/AdmissionTestCase's/AcademicTestCase's fixtures.
 */
abstract class ExaminationTestCase extends SisTestCase
{
    protected function createExamFixture(
        ?int $classId = null,
        ?int $academicSessionId = null,
        ?int $gradingSchemeId = null,
        ?string $examName = null,
        string $status = 'CONFIGURED',
    ): int {
        return (new ExamModel())->insert([
            'exam_name'           => $examName ?? ('Exam ' . uniqid('', true)),
            'class_id'            => $classId ?? $this->createClassFixture(),
            'academic_session_id' => $academicSessionId ?? $this->createAcademicSession(),
            'grading_scheme_id'   => $gradingSchemeId ?? $this->createGradingScheme(),
            'exam_date'           => date('Y-m-d'),
            'status'              => $status,
        ], true);
    }

    protected function createMarksRecordFixture(
        int $examId,
        ?int $studentId = null,
        ?int $subjectId = null,
        ?float $marksObtained = 80.0,
        float $maxMarks = 100.0,
        bool $isLocked = false,
        bool $isFlagged = false,
    ): int {
        return (new MarksRecordModel())->insert([
            'exam_id'        => $examId,
            'student_id'     => $studentId ?? $this->createStudentFixture(),
            'subject_id'     => $subjectId ?? $this->createSubject(),
            'marks_obtained' => $marksObtained,
            'max_marks'      => $maxMarks,
            'is_locked'      => $isLocked,
            'is_flagged'     => $isFlagged,
        ], true);
    }
}
