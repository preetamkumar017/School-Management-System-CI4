<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * @internal
 */
final class ReportCardTest extends ExaminationTestCase
{
    public function testPublishRequiresTheExamToBeLocked(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $examId  = $this->createExamFixture(null, null, null, null, 'ACTIVE');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/examination/report-cards/publish?exam_id={$examId}"),
            BusinessRuleException::class,
            'EXAM_NOT_LOCKED',
            422,
        );
    }

    /**
     * BR-EXM-001 end to end: lock -> publish -> Exam CLOSED, report cards
     * published, and the referenced GradingScheme becomes locked against
     * further mutation (ADR-005 §10 — Academic's own immutability check,
     * fed by Examination without a reverse dependency).
     */
    public function testPublishClosesExamAndLocksTheGradingScheme(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $schemeId   = $this->createGradingScheme();
        $examId     = $this->createExamFixture(null, null, $schemeId, null, 'ACTIVE');

        $this->createMarksRecordFixture($examId, null, null, 88.0, 100.0, true);

        $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/lock")->assertStatus(200);

        $publish = $this->withHeaders($headers)->post("api/v1/examination/report-cards/publish?exam_id={$examId}");
        $publish->assertStatus(200);
        $reportCards = $this->decode($publish)['data'];
        $this->assertCount(1, $reportCards);
        $this->assertTrue($reportCards[0]['is_published']);
        $this->assertNotNull($reportCards[0]['published_at']);

        $examShow = $this->withHeaders($headers)->get("api/v1/examination/exams/{$examId}");
        $this->assertSame('CLOSED', $this->decode($examShow)['data']['status']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/academic/grading-schemes/{$schemeId}", [
                'board_type'      => 'CBSE',
                'grade_band_json' => ['A1' => '90-100'],
            ]),
            BusinessRuleException::class,
            'GRADING_SCHEME_LOCKED_BY_CLOSED_EXAM',
            422,
        );
    }
}
