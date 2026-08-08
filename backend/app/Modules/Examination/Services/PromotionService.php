<?php

declare(strict_types=1);

namespace App\Modules\Examination\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Examination\DTOs\CreatePromotionRecordRequest;
use App\Modules\Examination\DTOs\PromotionRecordResponse;
use App\Modules\Examination\Entities\PromotionRecord;
use App\Modules\Examination\Models\PromotionRecordModel;
use Config\Services as AppServices;

/**
 * docs/design/examination/Phase-4-Service-Design.md
 * BR-SIS-001: promotion permitted only when both academic and fee closure
 * conditions are met. academic_closure_confirmed is system-computed
 * (ADR-005 §3); fee_closure_confirmed is now also system-computed by
 * querying Fees (ADR-014 §2) — no caller-supplied override.
 * RBAC (ADR-024 §3, Phase 2): `examination.manage` (Tier 1 only) gates
 * `promoteStudent()` — never self-service.
 */
class PromotionService
{
    public const PERMISSION_MANAGE = 'examination.manage';

    public function __construct(
        private readonly PromotionRecordModel $promotionRecordModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function promoteStudent(CreatePromotionRecordRequest $request): PromotionRecordResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        // Cross-module existence checks.
        AppServices::studentService()->assertStudentExists($request->studentId);
        $fromSession = AppServices::academicSessionService()->getSession($request->fromSessionId);
        AppServices::academicSessionService()->getSession($request->toSessionId);
        $fromClass = AppServices::classService()->getClass($request->fromClassId);
        $toClass   = AppServices::classService()->getClass($request->toClassId);

        if ($this->promotionRecordModel->existsByStudentAndFromSession($request->studentId, $request->fromSessionId)) {
            throw new BusinessRuleException(
                'PROMOTION_RECORD_ALREADY_EXISTS',
                'A promotion record already exists for this student from this academic session.',
            );
        }

        if ($toClass->sequenceOrder !== $fromClass->sequenceOrder + 1 && $toClass->sequenceOrder !== $fromClass->sequenceOrder) {
            throw new BusinessRuleException(
                'PROMOTION_INVALID_CLASS_SEQUENCE',
                'to_class_id must be the next class in sequence, or the same class (repeat).',
            );
        }

        $academicClosureConfirmed = $fromSession->status === 'CLOSED';
        $feeClosureConfirmed      = ! AppServices::invoiceService()->hasOutstandingBalance($request->studentId, $request->fromSessionId);

        if (! $academicClosureConfirmed || ! $feeClosureConfirmed) {
            throw new BusinessRuleException(
                'PROMOTION_CLOSURE_PRECONDITION_NOT_MET',
                'Both academic and fee closure must be confirmed before a student can be promoted (BR-SIS-001).',
            );
        }

        $id = $this->promotionRecordModel->insert([
            'student_id'                 => $request->studentId,
            'from_session_id'            => $request->fromSessionId,
            'to_session_id'              => $request->toSessionId,
            'from_class_id'              => $request->fromClassId,
            'to_class_id'                => $request->toClassId,
            'academic_closure_confirmed' => $academicClosureConfirmed,
            'fee_closure_confirmed'      => $feeClosureConfirmed,
        ], true);

        $promotionRecord = $this->promotionRecordModel->find($id);

        $this->auditService->record(
            'PromotionRecord',
            $id,
            AuditLog::ACTION_CREATE,
            null,
            $promotionRecord->toRawArray(),
        );

        return new PromotionRecordResponse($promotionRecord);
    }

    public function getPromotionRecord(int $id): PromotionRecordResponse
    {
        return new PromotionRecordResponse($this->requirePromotionRecord($id));
    }

    /**
     * @return list<PromotionRecordResponse>
     */
    public function listPromotionsByToSession(int $toSessionId): array
    {
        return array_map(
            static fn (PromotionRecord $promotionRecord): PromotionRecordResponse => new PromotionRecordResponse($promotionRecord),
            $this->promotionRecordModel->findByToSession($toSessionId),
        );
    }

    private function requirePromotionRecord(int $id): PromotionRecord
    {
        $promotionRecord = $this->promotionRecordModel->find($id);

        if ($promotionRecord === null) {
            throw new BusinessRuleException('PROMOTION_RECORD_NOT_FOUND', 'Promotion record not found.');
        }

        return $promotionRecord;
    }
}
