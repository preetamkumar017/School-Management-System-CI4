<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * @internal
 */
final class ExamTest extends ExaminationTestCase
{
    public function testCreateActivateAndReadExam(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sessionId = $this->createAcademicSession('2026-27', '2026-04-01', '2027-03-31');
        $schemeId  = $this->createGradingScheme();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/exams', [
            'exam_name'           => 'Term 1',
            'class_id'            => $classId,
            'academic_session_id' => $sessionId,
            'grading_scheme_id'   => $schemeId,
            'exam_date'           => '2026-09-15',
        ]);
        $create->assertStatus(201);
        $examId = $this->decode($create)['data']['exam_id'];
        $this->assertSame('CONFIGURED', $this->decode($create)['data']['status']);

        $activate = $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/activate");
        $activate->assertStatus(200);
        $this->assertSame('ACTIVE', $this->decode($activate)['data']['status']);

        $show = $this->withHeaders($headers)->get("api/v1/examination/exams/{$examId}");
        $show->assertStatus(200);
        $this->assertSame('Term 1', $this->decode($show)['data']['exam_name']);
    }

    public function testExamDateMustFallWithinAcademicSessionBounds(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sessionId = $this->createAcademicSession('2026-27', '2026-04-01', '2027-03-31');
        $schemeId  = $this->createGradingScheme();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/exams', [
                'exam_name'           => 'Out Of Bounds',
                'class_id'            => $classId,
                'academic_session_id' => $sessionId,
                'grading_scheme_id'   => $schemeId,
                'exam_date'           => '2028-01-01',
            ]),
            BusinessRuleException::class,
            'EXAM_DATE_OUTSIDE_ACADEMIC_SESSION',
            422,
        );
    }

    public function testLockRejectsWhenMarksAreIncomplete(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $examId  = $this->createExamFixture(null, null, null, null, 'ACTIVE');

        $this->createMarksRecordFixture($examId, null, null, 80.0, 100.0, false);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/lock"),
            BusinessRuleException::class,
            'EXAM_MARKS_NOT_COMPLETE',
            422,
        );
    }

    public function testLockComputesGradeGpaAndRank(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $subjectId = $this->createSubject();
        $examId    = $this->createExamFixture($classId, null, null, null, 'ACTIVE');

        $studentA = $this->createStudentFixture();
        $studentB = $this->createStudentFixture();

        // Student A: 90/100 = 90% -> grade point 9.0, GPA 9.0.
        $this->createMarksRecordFixture($examId, $studentA, $subjectId, 90.0, 100.0, true);
        // Student B: 70/100 = 70% -> grade point 7.0, GPA 7.0.
        $this->createMarksRecordFixture($examId, $studentB, $subjectId, 70.0, 100.0, true);

        $lock = $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/lock");
        $lock->assertStatus(200);
        $this->assertSame('LOCKED', $this->decode($lock)['data']['status']);

        $reportCards = $this->decode($this->withHeaders($headers)->get("api/v1/examination/report-cards?exam_id={$examId}"))['data'];
        $this->assertCount(2, $reportCards);

        $byStudent = [];
        foreach ($reportCards as $reportCard) {
            $byStudent[$reportCard['student_id']] = $reportCard;
        }

        $this->assertEquals(9.0, $byStudent[$studentA]['gpa']);
        $this->assertSame(1, $byStudent[$studentA]['class_rank']);
        $this->assertEquals(7.0, $byStudent[$studentB]['gpa']);
        $this->assertSame(2, $byStudent[$studentB]['class_rank']);
    }
}
