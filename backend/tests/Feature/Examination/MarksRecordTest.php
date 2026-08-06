<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * @internal
 */
final class MarksRecordTest extends ExaminationTestCase
{
    public function testCreateAndLockMarksRecord(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $examId    = $this->createExamFixture(null, null, null, null, 'ACTIVE');
        $studentId = $this->createStudentFixture();
        $subjectId = $this->createSubject();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/marks-records', [
            'exam_id'        => $examId,
            'student_id'     => $studentId,
            'subject_id'     => $subjectId,
            'marks_obtained' => 85,
            'max_marks'      => 100,
        ]);
        $create->assertStatus(201);
        $marksRecordId = $this->decode($create)['data']['marks_record_id'];
        $this->assertFalse($this->decode($create)['data']['is_locked']);

        $lock = $this->withHeaders($headers)->post("api/v1/examination/marks-records/{$marksRecordId}/lock");
        $lock->assertStatus(200);
        $this->assertTrue($this->decode($lock)['data']['is_locked']);
    }

    public function testMarksExceedingMaxMarksIsRejected(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $examId    = $this->createExamFixture(null, null, null, null, 'ACTIVE');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/marks-records', [
                'exam_id'        => $examId,
                'student_id'     => $this->createStudentFixture(),
                'subject_id'     => $this->createSubject(),
                'marks_obtained' => 150,
                'max_marks'      => 100,
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }

    public function testAnomalousMarkIsFlaggedAndBlocksLockUntilReevaluated(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $subjectId = $this->createSubject();

        // Historical locked mark: 90% in a prior exam.
        $priorExamId = $this->createExamFixture();
        $this->createMarksRecordFixture($priorExamId, $studentId, $subjectId, 90.0, 100.0, true);

        // New exam: 20% — a 70-point deviation, well past the 30-point threshold.
        $examId = $this->createExamFixture(null, null, null, null, 'ACTIVE');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/marks-records', [
            'exam_id'        => $examId,
            'student_id'     => $studentId,
            'subject_id'     => $subjectId,
            'marks_obtained' => 20,
            'max_marks'      => 100,
        ]);
        $create->assertStatus(201);
        $marksRecordId = $this->decode($create)['data']['marks_record_id'];
        $this->assertTrue($this->decode($create)['data']['is_flagged']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/examination/marks-records/{$marksRecordId}/lock"),
            BusinessRuleException::class,
            'MARKS_RECORD_FLAGGED_PENDING_REVIEW',
            422,
        );

        $reevaluate = $this->withHeaders($headers)->withBodyFormat('json')
            ->post("api/v1/examination/marks-records/{$marksRecordId}/reevaluate", [
                'marks_obtained' => 85,
                'reason'         => 'Transcription error corrected after review.',
            ]);
        $reevaluate->assertStatus(200);
        $body = $this->decode($reevaluate)['data'];
        $this->assertEquals(85.0, $body['marks_obtained']);
        $this->assertFalse($body['is_flagged']);
        $this->assertTrue($body['is_locked']);
    }

    public function testReevaluateRequiresAReason(): void
    {
        $user          = $this->createUser();
        $tokens        = $this->loginAs($user['username']);
        $headers       = $this->authHeaders($tokens['access_token']);
        $examId        = $this->createExamFixture();
        $marksRecordId = $this->createMarksRecordFixture($examId, null, null, 70.0, 100.0, true);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')
                ->post("api/v1/examination/marks-records/{$marksRecordId}/reevaluate", [
                    'marks_obtained' => 75,
                    'reason'         => '',
                ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }
}
