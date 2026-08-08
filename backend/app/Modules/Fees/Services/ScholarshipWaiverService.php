<?php

declare(strict_types=1);

namespace App\Modules\Fees\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Fees\DTOs\CreateScholarshipWaiverRequest;
use App\Modules\Fees\DTOs\ScholarshipWaiverResponse;
use App\Modules\Fees\Entities\ScholarshipWaiver;
use App\Modules\Fees\Models\FeeHeadModel;
use App\Modules\Fees\Models\ScholarshipWaiverModel;
use Config\Services as AppServices;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * RBAC (ADR-024 §3, Phase 2): `fees.manage` (Tier 1) gates `createWaiver()`
 * (staff only). `getWaiver()`/`listByStudent()` allow Tier 2 read-only —
 * a Student may read their own scholarship waivers.
 */
class ScholarshipWaiverService
{
    public const PERMISSION_MANAGE = 'fees.manage';

    public function __construct(
        private readonly ScholarshipWaiverModel $scholarshipWaiverModel,
        private readonly FeeHeadModel $feeHeadModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createWaiver(CreateScholarshipWaiverRequest $request): ScholarshipWaiverResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        AppServices::studentService()->assertStudentExists($request->studentId);

        if ($this->feeHeadModel->find($request->feeHeadId) === null) {
            throw new BusinessRuleException('FEE_HEAD_NOT_FOUND', 'Fee head not found.');
        }

        $id = $this->scholarshipWaiverModel->insert([
            'student_id'    => $request->studentId,
            'fee_head_id'   => $request->feeHeadId,
            'waiver_type'   => $request->waiverType,
            'waiver_amount' => $request->waiverAmount,
        ], true);

        $waiver = $this->scholarshipWaiverModel->find($id);

        $this->auditService->record('ScholarshipWaiver', $id, AuditLog::ACTION_CREATE, null, $waiver->toRawArray());

        return new ScholarshipWaiverResponse($waiver);
    }

    public function getWaiver(int $id): ScholarshipWaiverResponse
    {
        $waiver = $this->requireWaiver($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $waiver->student_id);

        return new ScholarshipWaiverResponse($waiver);
    }

    /**
     * @return list<ScholarshipWaiverResponse>
     */
    public function listByStudent(int $studentId): array
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $studentId);

        return array_map(
            static fn (ScholarshipWaiver $waiver): ScholarshipWaiverResponse => new ScholarshipWaiverResponse($waiver),
            $this->scholarshipWaiverModel->findByStudentId($studentId),
        );
    }

    private function requireWaiver(int $id): ScholarshipWaiver
    {
        $waiver = $this->scholarshipWaiverModel->find($id);

        if ($waiver === null) {
            throw new BusinessRuleException('SCHOLARSHIP_WAIVER_NOT_FOUND', 'Scholarship waiver not found.');
        }

        return $waiver;
    }
}
