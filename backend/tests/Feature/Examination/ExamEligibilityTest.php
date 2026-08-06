<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Attendance\AttendanceTestCase;

/**
 * @internal
 * docs/ADR/ADR-006-timetable-and-attendance-scope-decisions.md §11 — the
 * seam docs/ADR/ADR-005 §2 left as an always-eligible stub, now closed:
 * MarksRecordService calls AttendanceService::isExamEligibilityAtRisk for
 * real (a genuine cross-module Examination -> Attendance call).
 */
final class ExamEligibilityTest extends AttendanceTestCase
{
    public function testMarksEntryIsBlockedForAnAtRiskStudentWithoutOverride(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $sessionId = $this->createAcademicSession('2026-27', '2026-04-01', '2027-03-31');
        $examId    = $this->createExamFixture(null, $sessionId, null, null, 'ACTIVE');
        $subjectId = $this->createSubject();

        // 1 present out of 4 = 25%, below the 75% threshold, within the
        // session's own date bounds.
        $this->createAttendanceRecordFixture($studentId, null, '2026-04-05', 'PRESENT');
        $this->createAttendanceRecordFixture($studentId, null, '2026-04-06', 'ABSENT');
        $this->createAttendanceRecordFixture($studentId, null, '2026-04-07', 'ABSENT');
        $this->createAttendanceRecordFixture($studentId, null, '2026-04-08', 'ABSENT');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/marks-records', [
                'exam_id'        => $examId,
                'student_id'     => $studentId,
                'subject_id'     => $subjectId,
                'marks_obtained' => 80,
                'max_marks'      => 100,
            ]),
            BusinessRuleException::class,
            'STUDENT_EXAM_ELIGIBILITY_AT_RISK',
            422,
        );

        $withOverride = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/marks-records', [
            'exam_id'          => $examId,
            'student_id'       => $studentId,
            'subject_id'       => $subjectId,
            'marks_obtained'   => 80,
            'max_marks'        => 100,
            'override_reason'  => 'Academic Head approved exception.',
        ]);
        $withOverride->assertStatus(201);
    }
}
