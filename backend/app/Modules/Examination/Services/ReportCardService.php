<?php

declare(strict_types=1);

namespace App\Modules\Examination\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\RequestContext;
use App\Core\Pdf\PdfRenderer;
use App\Modules\Administration\DTOs\DocumentResponse;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\DocumentService;
use App\Modules\Examination\DTOs\ReportCardResponse;
use App\Modules\Examination\Entities\Exam;
use App\Modules\Examination\Entities\ReportCard;
use App\Modules\Examination\Models\ExamModel;
use App\Modules\Examination\Models\ReportCardModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/examination/Phase-4-Service-Design.md
 * No createReportCard — rows are produced only by ExamService::lockExam.
 * RBAC (ADR-024 §3, Phase 2): `examination.manage` (Tier 1) gates
 * `publishReportCards()`/`listReportCardsByExam()` (class-wide, staff
 * only). `getReportCard()`/`generatePdf()` allow Tier 2 — a Student may
 * read/download their own report card.
 */
class ReportCardService
{
    use ClosedSessionGuard;

    public const PERMISSION_MANAGE = 'examination.manage';

    public function __construct(
        private readonly ReportCardModel $reportCardModel,
        private readonly ExamModel $examModel,
        private readonly AuditService $auditService,
        private readonly DocumentService $documentService,
        private readonly PdfRenderer $pdfRenderer,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function getReportCard(int $id): ReportCardResponse
    {
        $reportCard = $this->requireReportCard($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $reportCard->student_id);

        return new ReportCardResponse($reportCard);
    }

    /**
     * ADR-012 §3: renders a plain, functional PDF from the same fields
     * ReportCardResponse already exposes — no school branding asset
     * exists to include (same reasoning FR-09 is deferred, ADR-012 §4).
     */
    public function generatePdf(int $id): DocumentResponse
    {
        $reportCard = $this->requireReportCard($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $reportCard->student_id);

        $exam    = $this->requireExam($reportCard->exam_id);
        $student = AppServices::studentService()->getStudentForCrossModuleRead($reportCard->student_id);

        $rows = '';

        foreach ($reportCard->grade_summary as $subject => $grade) {
            $rows .= '<tr><td>' . htmlspecialchars((string) $subject) . '</td><td>' . htmlspecialchars((string) $grade) . '</td></tr>';
        }

        $html = '<html><body>'
            . '<h2>Report Card</h2>'
            . '<p><strong>Student:</strong> ' . htmlspecialchars($student->fullName) . ' (' . htmlspecialchars($student->admissionNumber) . ')</p>'
            . '<p><strong>Exam:</strong> ' . htmlspecialchars($exam->exam_name) . '</p>'
            . '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; width: 100%;">'
            . '<thead><tr><th>Subject</th><th>Grade</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p><strong>GPA:</strong> ' . htmlspecialchars((string) $reportCard->gpa) . '</p>'
            . '<p><strong>Class Rank:</strong> ' . htmlspecialchars((string) ($reportCard->class_rank ?? 'N/A')) . '</p>'
            . '</body></html>';

        $pdfBytes = $this->pdfRenderer->render($html);

        return $this->documentService->store(
            'ReportCard',
            $id,
            'Report Card',
            $pdfBytes,
            (int) RequestContext::userId(),
        );
    }

    /**
     * @return list<ReportCardResponse>
     */
    public function listReportCardsByExam(int $examId): array
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        return $this->listReportCardsByExamForCrossModuleRead($examId);
    }

    /**
     * Ungated cross-module aggregate read — Reports'
     * `getAcademicPerformance()` composes this under its own
     * `reports.manage` gate, not Examination's; a caller with only
     * `reports.manage` (no `examination.manage`) must still be able to
     * pull this aggregate (ADR-024 Phase 1 Addendum's "existence/count
     * helper" precedent, extended to this cross-module list read).
     *
     * @return list<ReportCardResponse>
     */
    public function listReportCardsByExamForCrossModuleRead(int $examId): array
    {
        return array_map(
            static fn (ReportCard $reportCard): ReportCardResponse => new ReportCardResponse($reportCard),
            $this->reportCardModel->findByExamId($examId),
        );
    }

    /**
     * BR-EXM-001: guards Exam.status = LOCKED (every subject's marks are
     * locked and report cards already computed by ExamService::lockExam).
     * Publishes every ReportCard for the exam and transitions
     * Exam.status LOCKED -> CLOSED. Also locks the referenced
     * GradingScheme against further mutation (ADR-005 §10) — the one
     * place Examination calls outward into Academic to report state,
     * keeping the dependency one-way.
     *
     * @return list<ReportCardResponse>
     */
    public function publishReportCards(int $examId, ?string $overrideReason = null): array
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $exam = $this->requireExam($examId);
        $this->assertSessionMutable($exam->academic_session_id, $overrideReason);

        if ($exam->status !== Exam::STATUS_LOCKED) {
            throw new BusinessRuleException(
                'EXAM_NOT_LOCKED',
                'Report cards can only be published once the exam is LOCKED (BR-EXM-001).',
            );
        }

        $reportCards = $this->reportCardModel->findByExamId($examId);

        if ($reportCards === []) {
            throw new BusinessRuleException('NO_REPORT_CARDS_TO_PUBLISH', 'No report cards exist for this exam.');
        }

        $now = Time::now()->toDateTimeString();

        foreach ($reportCards as $reportCard) {
            $this->reportCardModel->update($reportCard->report_card_id, [
                'is_published' => true,
                'published_at' => $now,
            ]);
        }

        $beforeExam = $exam->toRawArray();
        $this->examModel->update($examId, ['status' => Exam::STATUS_CLOSED]);
        $afterExam = $this->examModel->find($examId);

        AppServices::gradingSchemeService()->lockSchemeReferencedByClosedExam($exam->grading_scheme_id);

        $this->auditService->record('Exam', $examId, AuditLog::ACTION_APPROVE, $beforeExam, $afterExam->toRawArray());

        return array_map(
            fn (ReportCard $reportCard): ReportCardResponse => new ReportCardResponse(
                $this->requireReportCard((int) $reportCard->report_card_id),
            ),
            $reportCards,
        );
    }

    private function requireReportCard(int $id): ReportCard
    {
        $reportCard = $this->reportCardModel->find($id);

        if ($reportCard === null) {
            throw new BusinessRuleException('REPORT_CARD_NOT_FOUND', 'Report card not found.');
        }

        return $reportCard;
    }

    private function requireExam(int $id): Exam
    {
        $exam = $this->examModel->find($id);

        if ($exam === null) {
            throw new BusinessRuleException('EXAM_NOT_FOUND', 'Exam not found.');
        }

        return $exam;
    }
}
