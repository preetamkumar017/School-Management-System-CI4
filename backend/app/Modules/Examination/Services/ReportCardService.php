<?php

declare(strict_types=1);

namespace App\Modules\Examination\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
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
 */
class ReportCardService
{
    use ClosedSessionGuard;

    public function __construct(
        private readonly ReportCardModel $reportCardModel,
        private readonly ExamModel $examModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function getReportCard(int $id): ReportCardResponse
    {
        return new ReportCardResponse($this->requireReportCard($id));
    }

    /**
     * @return list<ReportCardResponse>
     */
    public function listReportCardsByExam(int $examId): array
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
