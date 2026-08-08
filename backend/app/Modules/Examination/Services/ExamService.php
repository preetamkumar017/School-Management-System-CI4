<?php

declare(strict_types=1);

namespace App\Modules\Examination\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Examination\DTOs\CreateExamRequest;
use App\Modules\Examination\DTOs\ExamResponse;
use App\Modules\Examination\Entities\Exam;
use App\Modules\Examination\Entities\MarksRecord;
use App\Modules\Examination\Models\ExamModel;
use App\Modules\Examination\Models\MarksRecordModel;
use App\Modules\Examination\Models\ReportCardModel;
use Config\Database;
use Config\Services as AppServices;

/**
 * docs/design/examination/Phase-4-Service-Design.md
 * RBAC (ADR-024 §3, Phase 2): `examination.manage` (Tier 1 only) gates
 * every write — Exam has no per-caller owner. Reads unchanged.
 */
class ExamService
{
    use ClosedSessionGuard;

    public const PERMISSION_MANAGE = 'examination.manage';

    public function __construct(
        private readonly ExamModel $examModel,
        private readonly MarksRecordModel $marksRecordModel,
        private readonly ReportCardModel $reportCardModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createExam(CreateExamRequest $request): ExamResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        AppServices::classService()->getClass($request->classId);
        $session = AppServices::academicSessionService()->getSession($request->academicSessionId);
        AppServices::gradingSchemeService()->getGradingScheme($request->gradingSchemeId);

        if ($this->examModel->existsByClassExamNameSession($request->classId, $request->examName, $request->academicSessionId)) {
            throw new BusinessRuleException(
                'EXAM_ALREADY_EXISTS',
                'An exam with this name already exists for this class and academic session.',
            );
        }

        $examTimestamp = strtotime($request->examDate);
        $startTimestamp = strtotime($session->startDate);
        $endTimestamp   = strtotime($session->endDate);

        if ($examTimestamp === false || $examTimestamp < $startTimestamp || $examTimestamp > $endTimestamp) {
            throw new BusinessRuleException(
                'EXAM_DATE_OUTSIDE_ACADEMIC_SESSION',
                'exam_date must fall within the academic session\'s date bounds.',
            );
        }

        $id = $this->examModel->insert([
            'exam_name'           => $request->examName,
            'class_id'            => $request->classId,
            'academic_session_id' => $request->academicSessionId,
            'grading_scheme_id'   => $request->gradingSchemeId,
            'exam_date'           => $request->examDate,
            'status'              => Exam::STATUS_CONFIGURED,
        ], true);

        $exam = $this->examModel->find($id);

        $this->auditService->record('Exam', $id, AuditLog::ACTION_CREATE, null, $exam->toRawArray());

        return new ExamResponse($exam);
    }

    public function activateExam(int $id, ?string $overrideReason = null): ExamResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        return $this->transition($id, Exam::STATUS_CONFIGURED, Exam::STATUS_ACTIVE, $overrideReason);
    }

    /**
     * ADR-005 §8: completeness is "every entered MarksRecord is locked,"
     * not a full Academic/SIS roster cross-reference. On success,
     * computes grade/GPA/rank per student and upserts their ReportCard
     * (FR-19) inside the same transaction as the status transition.
     */
    public function lockExam(int $id, ?string $overrideReason = null): ExamResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireExam($id);
        $this->assertSessionMutable($before->academic_session_id, $overrideReason);

        if ($before->status !== Exam::STATUS_ACTIVE) {
            throw new BusinessRuleException(
                'EXAM_INVALID_STATUS_TRANSITION',
                "Cannot lock an exam in status {$before->status}.",
            );
        }

        $marksRecords = $this->marksRecordModel->findByExamId($id);

        if ($marksRecords === [] || $this->marksRecordModel->countUnlockedByExamId($id) > 0) {
            throw new BusinessRuleException(
                'EXAM_MARKS_NOT_COMPLETE',
                'Every entered marks record for this exam must be locked before it can be locked.',
            );
        }

        $db = Database::connect();
        $db->transStart();

        $this->recalculateReportCards($id);

        $this->examModel->update($id, ['status' => Exam::STATUS_LOCKED]);
        $after = $this->examModel->find($id);

        $this->auditService->record('Exam', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        if ($overrideReason !== null) {
            $this->auditService->record('Exam', $id, AuditLog::ACTION_OVERRIDE, null, null, $overrideReason);
        }

        $db->transComplete();

        return new ExamResponse($after);
    }

    public function getExam(int $id): ExamResponse
    {
        return new ExamResponse($this->requireExam($id));
    }

    /**
     * @return list<ExamResponse>
     */
    public function listExamsByClassAndSession(int $classId, int $academicSessionId): array
    {
        return array_map(
            static fn (Exam $exam): ExamResponse => new ExamResponse($exam),
            $this->examModel->findByClassAndSession($classId, $academicSessionId),
        );
    }

    /**
     * Recomputes grade/GPA/rank for every student with marks in this exam
     * and upserts their ReportCard row (FR-19, BR-EXM-004). Called by
     * lockExam (full-class, on first completion) and by
     * MarksRecordService::reevaluate (also full-class, since one
     * student's GPA shift can change others' relative rank — ADR-005 §7).
     * Public so MarksRecordService (same module) can call it without
     * duplicating the calculation.
     */
    public function recalculateReportCards(int $examId): void
    {
        $exam          = $this->requireExam($examId);
        $gradingScheme = AppServices::gradingSchemeService()->getGradingScheme($exam->grading_scheme_id);
        $marksRecords  = $this->marksRecordModel->findByExamId($examId);
        $gradeBandJson = $gradingScheme->gradeBandJson;

        /** @var array<int, list<MarksRecord>> $byStudent */
        $byStudent = [];

        foreach ($marksRecords as $marksRecord) {
            $byStudent[$marksRecord->student_id][] = $marksRecord;
        }

        /** @var array<int, float> $gpaByStudent */
        $gpaByStudent = [];
        /** @var array<int, array<string, string>> $gradeSummaryByStudent */
        $gradeSummaryByStudent = [];

        foreach ($byStudent as $studentId => $studentMarks) {
            $gradeSummary = [];
            $gradePoints  = [];

            foreach ($studentMarks as $marksRecord) {
                $obtained   = $marksRecord->marks_obtained ?? 0.0;
                $percentage = $marksRecord->max_marks > 0 ? ($obtained / $marksRecord->max_marks) * 100 : 0.0;

                $gradeSummary['subject_' . $marksRecord->subject_id] = $this->resolveGrade($percentage, $gradeBandJson);
                $gradePoints[] = min(9.99, round($percentage / 10, 2));
            }

            $gradeSummaryByStudent[$studentId] = $gradeSummary;
            $gpaByStudent[$studentId]          = round(array_sum($gradePoints) / count($gradePoints), 2);
        }

        // Standard competition ranking ("1224") by GPA descending (ADR-005 §5).
        $sortedStudentIds = array_keys($gpaByStudent);
        usort($sortedStudentIds, static fn (int $a, int $b): int => $gpaByStudent[$b] <=> $gpaByStudent[$a]);

        $rankByStudent = [];
        $rank          = 0;
        $previousGpa   = null;

        foreach ($sortedStudentIds as $position => $studentId) {
            if ($previousGpa === null || $gpaByStudent[$studentId] < $previousGpa) {
                $rank = $position + 1;
            }

            $rankByStudent[$studentId] = $rank;
            $previousGpa               = $gpaByStudent[$studentId];
        }

        foreach ($byStudent as $studentId => $studentMarks) {
            $existing = $this->reportCardModel->findByStudentAndExam($studentId, $examId);

            $data = [
                'student_id'    => $studentId,
                'exam_id'       => $examId,
                'grade_summary' => $gradeSummaryByStudent[$studentId],
                'gpa'           => $gpaByStudent[$studentId],
                'class_rank'    => $rankByStudent[$studentId],
            ];

            if ($existing === null) {
                $this->reportCardModel->insert($data);
            } else {
                $this->reportCardModel->update($existing->report_card_id, $data);
            }
        }
    }

    /**
     * @param array<string, string> $gradeBandJson
     */
    private function resolveGrade(float $percentage, array $gradeBandJson): string
    {
        foreach ($gradeBandJson as $grade => $band) {
            if (preg_match('/^(\d+)-(\d+)$/', $band, $matches) !== 1) {
                continue;
            }

            if ($percentage >= (float) $matches[1] && $percentage <= (float) $matches[2]) {
                return $grade;
            }
        }

        return 'UNGRADED';
    }

    private function transition(int $id, string $from, string $to, ?string $overrideReason): ExamResponse
    {
        $before = $this->requireExam($id);
        $this->assertSessionMutable($before->academic_session_id, $overrideReason);

        if ($before->status !== $from) {
            throw new BusinessRuleException(
                'EXAM_INVALID_STATUS_TRANSITION',
                "Cannot transition an exam from {$before->status} to {$to}.",
            );
        }

        $this->examModel->update($id, ['status' => $to]);
        $after = $this->examModel->find($id);

        $this->auditService->record('Exam', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new ExamResponse($after);
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
