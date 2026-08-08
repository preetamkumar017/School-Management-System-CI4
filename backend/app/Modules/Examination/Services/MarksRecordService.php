<?php

declare(strict_types=1);

namespace App\Modules\Examination\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Examination\DTOs\CreateMarksRecordRequest;
use App\Modules\Examination\DTOs\MarksRecordReevaluateRequest;
use App\Modules\Examination\DTOs\MarksRecordResponse;
use App\Modules\Examination\Entities\Exam;
use App\Modules\Examination\Entities\MarksRecord;
use App\Modules\Examination\Models\ExamModel;
use App\Modules\Examination\Models\MarksRecordModel;
use Config\Services as AppServices;

/**
 * docs/design/examination/Phase-4-Service-Design.md
 * RBAC (ADR-024 §3, Phase 2): `examination.manage` (Tier 1 only) gates
 * entry/lock/reevaluate — never self-service. `getMarksRecord()` allows
 * Tier 2 — a Student may read their own MarksRecord (SIS Student-self
 * ownership chain; no Guardian login exists in this system, see SIS's
 * StudentService docblock).
 */
class MarksRecordService
{
    use ClosedSessionGuard;

    public const PERMISSION_MANAGE = 'examination.manage';

    public function __construct(
        private readonly MarksRecordModel $marksRecordModel,
        private readonly ExamModel $examModel,
        private readonly ExamService $examService,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createMarksRecord(CreateMarksRecordRequest $request, ?string $overrideReason = null): MarksRecordResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $exam = $this->requireExam($request->examId);
        $this->assertSessionMutable($exam->academic_session_id, null);

        if (in_array($exam->status, [Exam::STATUS_LOCKED, Exam::STATUS_CLOSED], true)) {
            throw new BusinessRuleException(
                'EXAM_ALREADY_LOCKED',
                'Marks cannot be entered once the exam is locked or closed.',
            );
        }

        // Cross-module existence checks — SIS's StudentService exposes no
        // dedicated "exists" method, so getStudent's own STUDENT_NOT_FOUND
        // is reused, same pattern as every other cross-module check.
        AppServices::studentService()->assertStudentExists($request->studentId);
        AppServices::subjectService()->getSubject($request->subjectId);

        // BR-EXM-005 eligibility precondition — real check as of ADR-006
        // §11 (Attendance now exists). A flagged student can still be
        // marked, but only with a logged Academic Head override, exactly
        // as BR-EXM-005's text requires.
        $session = AppServices::academicSessionService()->getSession($exam->academic_session_id);

        if (AppServices::attendanceService()->isExamEligibilityAtRisk($request->studentId, $session->startDate, (string) $exam->exam_date)) {
            if ($overrideReason === null || trim($overrideReason) === '') {
                throw new BusinessRuleException(
                    'STUDENT_EXAM_ELIGIBILITY_AT_RISK',
                    'This student is below the minimum attendance threshold (BR-ATT-006). '
                        . 'Supply override_reason to proceed (BR-EXM-005).',
                );
            }

            $this->auditService->record(
                'MarksRecord',
                $request->studentId,
                AuditLog::ACTION_OVERRIDE,
                null,
                null,
                $overrideReason,
            );
        }

        if ($this->marksRecordModel->existsByExamStudentSubject($request->examId, $request->studentId, $request->subjectId)) {
            throw new BusinessRuleException(
                'MARKS_RECORD_ALREADY_EXISTS',
                'A marks record already exists for this student/subject/exam.',
            );
        }

        if ($request->marksObtained !== null && ($request->marksObtained < 0 || $request->marksObtained > $request->maxMarks)) {
            throw new ValidationException(
                ['marks_obtained' => 'marks_obtained must be between 0 and max_marks.'],
            );
        }

        $isFlagged = $request->marksObtained !== null && $this->isAnomalous(
            $request->studentId,
            $request->subjectId,
            $request->examId,
            $request->marksObtained,
            $request->maxMarks,
        );

        $id = $this->marksRecordModel->insert([
            'exam_id'        => $request->examId,
            'student_id'     => $request->studentId,
            'subject_id'     => $request->subjectId,
            'marks_obtained' => $request->marksObtained,
            'max_marks'      => $request->maxMarks,
            'is_flagged'     => $isFlagged,
        ], true);

        $marksRecord = $this->marksRecordModel->find($id);

        $this->auditService->record('MarksRecord', $id, AuditLog::ACTION_CREATE, null, $marksRecord->toRawArray());

        return new MarksRecordResponse($marksRecord);
    }

    public function lockMarksRecord(int $id, ?string $overrideReason = null): MarksRecordResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireMarksRecord($id);
        $exam   = $this->requireExam($before->exam_id);
        $this->assertSessionMutable($exam->academic_session_id, $overrideReason);

        if ($before->is_locked) {
            throw new BusinessRuleException('MARKS_RECORD_ALREADY_LOCKED', 'This marks record is already locked.');
        }

        if ($before->is_flagged) {
            throw new BusinessRuleException(
                'MARKS_RECORD_FLAGGED_PENDING_REVIEW',
                'This marks record is flagged as anomalous and must be reviewed (via re-evaluation) before it can be locked.',
            );
        }

        $this->marksRecordModel->update($id, ['is_locked' => true]);
        $after = $this->marksRecordModel->find($id);

        $this->auditService->record('MarksRecord', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new MarksRecordResponse($after);
    }

    /**
     * ADR-005 §7 — the single logged action standing in for BR-EXM-003's
     * "re-evaluation workflow." Unlocks, updates, re-locks in one call.
     * If the parent Exam is already LOCKED/CLOSED, recomputes grades/
     * GPA/rank for the whole class (BR-EXM-004) — one student's changed
     * mark can shift others' relative rank.
     */
    public function reevaluate(int $id, MarksRecordReevaluateRequest $request): MarksRecordResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireMarksRecord($id);
        $exam   = $this->requireExam($before->exam_id);
        $this->assertSessionMutable($exam->academic_session_id, $request->reason);

        if ($request->marksObtained < 0 || $request->marksObtained > $before->max_marks) {
            throw new ValidationException(
                ['marks_obtained' => 'marks_obtained must be between 0 and max_marks.'],
            );
        }

        $this->marksRecordModel->update($id, [
            'marks_obtained' => $request->marksObtained,
            'is_flagged'     => false,
            'is_locked'      => true,
        ]);

        $after = $this->marksRecordModel->find($id);

        $this->auditService->record(
            'MarksRecord',
            $id,
            AuditLog::ACTION_OVERRIDE,
            $before->toRawArray(),
            $after->toRawArray(),
            $request->reason,
        );

        if (in_array($exam->status, [Exam::STATUS_LOCKED, Exam::STATUS_CLOSED], true)) {
            $this->examService->recalculateReportCards($exam->exam_id);
        }

        return new MarksRecordResponse($after);
    }

    public function getMarksRecord(int $id): MarksRecordResponse
    {
        $marksRecord = $this->requireMarksRecord($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $marksRecord->student_id);

        return new MarksRecordResponse($marksRecord);
    }

    /**
     * @return list<MarksRecordResponse>
     */
    public function listMarksByExam(int $examId): array
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        return array_map(
            static fn (MarksRecord $marksRecord): MarksRecordResponse => new MarksRecordResponse($marksRecord),
            $this->marksRecordModel->findByExamId($examId),
        );
    }

    private function isAnomalous(int $studentId, int $subjectId, int $examId, float $marksObtained, float $maxMarks): bool
    {
        $historical = $this->marksRecordModel->findLockedByStudentAndSubjectExceptExam($studentId, $subjectId, $examId);

        if ($historical === []) {
            return false;
        }

        $historicalPercentages = array_map(
            static fn (MarksRecord $record): float => $record->max_marks > 0
                ? (($record->marks_obtained ?? 0.0) / $record->max_marks) * 100
                : 0.0,
            $historical,
        );

        $historicalAverage = array_sum($historicalPercentages) / count($historicalPercentages);
        $newPercentage     = $maxMarks > 0 ? ($marksObtained / $maxMarks) * 100 : 0.0;

        return abs($newPercentage - $historicalAverage) > $this->configurationService->getNumber('examination.anomaly_threshold_percentage_points');
    }

    private function requireMarksRecord(int $id): MarksRecord
    {
        $marksRecord = $this->marksRecordModel->find($id);

        if ($marksRecord === null) {
            throw new BusinessRuleException('MARKS_RECORD_NOT_FOUND', 'Marks record not found.');
        }

        return $marksRecord;
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
