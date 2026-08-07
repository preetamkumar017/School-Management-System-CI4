<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Modules\Examination\Models\ReportCardModel;
use Config\Services;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * docs/ADR/ADR-012-document-pdf-module-scope-decisions.md
 *
 * @internal
 */
final class ReportCardPdfTest extends ExaminationTestCase
{
    public function testGeneratePdfCreatesDownloadableDocument(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $examId  = $this->createExamFixture();

        $reportCardId = (new ReportCardModel())->insert([
            'student_id'    => $this->createStudentFixture(),
            'exam_id'       => $examId,
            'grade_summary' => ['Maths' => 'A1', 'Science' => 'A2'],
            'gpa'           => 9.5,
            'class_rank'    => 1,
            'is_published'  => false,
        ], true);

        $generate = $this->withHeaders($headers)->post("api/v1/examination/report-cards/{$reportCardId}/generate-pdf");
        $generate->assertStatus(201);
        $body = $this->decode($generate)['data'];
        $this->assertSame('ReportCard', $body['owner_type']);
        $this->assertSame($reportCardId, $body['owner_ref_id']);
        $this->assertSame('Report Card', $body['document_type']);

        $documentId = $body['document_id'];

        $download = $this->withHeaders($headers)->get("api/v1/administration/documents/{$documentId}/download");
        $download->assertStatus(200);

        $absolutePath = Services::documentService()->getAbsolutePath($documentId);
        $this->assertFileExists($absolutePath);
        $this->assertStringStartsWith('%PDF', file_get_contents($absolutePath));
    }
}
