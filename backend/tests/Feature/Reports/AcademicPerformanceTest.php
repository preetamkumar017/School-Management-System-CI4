<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Tests\Support\Examination\ExaminationTestCase;
use Tests\Support\Reports\ReportsExportAssertions;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 4. GPA/class_rank
 * are produced by the real ExamService::lockExam -> recalculateReportCards
 * flow (Stage 6a's decided formula), not hand-crafted — Reports only
 * aggregates over already-computed ReportCard rows.
 *
 * @internal
 */
final class AcademicPerformanceTest extends ExaminationTestCase
{
    use ReportsExportAssertions;

    public function testAcademicPerformanceComputesExactAverageGpaAndPassFail(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $subjectId = $this->createSubject();
        $examId    = $this->createExamFixture($classId, null, null, null, 'ACTIVE');

        $studentA = $this->createStudentFixture();
        $studentB = $this->createStudentFixture();
        $studentC = $this->createStudentFixture();

        // Student A: 90/100 = 90% -> grade point 9.0 (pass, >= 4.0).
        $this->createMarksRecordFixture($examId, $studentA, $subjectId, 90.0, 100.0, true);
        // Student B: 70/100 = 70% -> grade point 7.0 (pass).
        $this->createMarksRecordFixture($examId, $studentB, $subjectId, 70.0, 100.0, true);
        // Student C: 20/100 = 20% -> grade point 2.0 (fail, < 4.0).
        $this->createMarksRecordFixture($examId, $studentC, $subjectId, 20.0, 100.0, true);

        $lock = $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/lock");
        $lock->assertStatus(200);

        $response = $this->withHeaders($headers)->get("api/v1/reports/academic-performance?exam_id={$examId}");
        $response->assertStatus(200);
        $body = $this->decode($response)['data'];

        $this->assertSame(3, $body['report_card_count']);
        // (9.0 + 7.0 + 2.0) / 3 = 6.0
        $this->assertEquals(6.0, $body['average_gpa']);
        $this->assertSame(2, $body['pass_count']);
        $this->assertSame(1, $body['fail_count']);
        $this->assertEquals(4.0, $body['pass_threshold_gpa']);

        // Standard competition ranking by GPA descending: A=1, B=2, C=3.
        $this->assertSame(1, $body['rank_distribution']['1']);
        $this->assertSame(1, $body['rank_distribution']['2']);
        $this->assertSame(1, $body['rank_distribution']['3']);
    }

    public function testAcademicPerformancePdfExportProducesValidPdf(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $examId  = $this->createExamFixture(null, null, null, null, 'ACTIVE');
        $this->createMarksRecordFixture($examId, null, null, 80.0, 100.0, true);
        $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/lock");

        $response = $this->withHeaders($headers)->get("api/v1/reports/academic-performance/pdf?exam_id={$examId}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        $this->assertSame('%PDF', substr($body, 0, 4));
    }

    public function testAcademicPerformanceExcelExportProducesValidXlsx(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $examId  = $this->createExamFixture(null, null, null, null, 'ACTIVE');
        $this->createMarksRecordFixture($examId, null, null, 80.0, 100.0, true);
        $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/lock");

        $response = $this->withHeaders($headers)->get("api/v1/reports/academic-performance/excel?exam_id={$examId}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        $this->assertSame("PK\x03\x04", substr($body, 0, 4));
    }
}
