<?php

declare(strict_types=1);

namespace App\Modules\Fees\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Fees\DTOs\CreateFeeStructureRequest;
use App\Modules\Fees\DTOs\FeeStructureResponse;
use App\Modules\Fees\DTOs\UpdateFeeStructureRequest;
use App\Modules\Fees\Entities\FeeStructure;
use App\Modules\Fees\Models\FeeHeadModel;
use App\Modules\Fees\Models\FeeStructureModel;
use Config\Services as AppServices;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 */
class FeeStructureService
{
    public function __construct(
        private readonly FeeStructureModel $feeStructureModel,
        private readonly FeeHeadModel $feeHeadModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createFeeStructure(CreateFeeStructureRequest $request): FeeStructureResponse
    {
        AppServices::classService()->getClass($request->classId);
        AppServices::academicSessionService()->getSession($request->academicSessionId);

        if ($this->feeHeadModel->find($request->feeHeadId) === null) {
            throw new BusinessRuleException('FEE_HEAD_NOT_FOUND', 'Fee head not found.');
        }

        // ADR-014 §1: route_id is an additive, optional tier key. A route
        // must be a real Route before a fee tier can be pinned to it.
        if ($request->routeId !== null) {
            AppServices::routeService()->getRoute($request->routeId);
        }

        if ($this->feeStructureModel->existsByClassHeadSessionCategory($request->classId, $request->feeHeadId, $request->academicSessionId, $request->category, $request->routeId)) {
            throw new BusinessRuleException(
                'FEE_STRUCTURE_ALREADY_EXISTS',
                'A fee structure already exists for this class, fee head, session, category, and route.',
            );
        }

        $id = $this->feeStructureModel->insert([
            'class_id'            => $request->classId,
            'fee_head_id'         => $request->feeHeadId,
            'academic_session_id' => $request->academicSessionId,
            'route_id'            => $request->routeId,
            'category'            => $request->category,
            'amount'              => $request->amount,
        ], true);

        $feeStructure = $this->feeStructureModel->find($id);

        $this->auditService->record('FeeStructure', $id, AuditLog::ACTION_CREATE, null, $feeStructure->toRawArray());

        return new FeeStructureResponse($feeStructure);
    }

    public function updateFeeStructure(int $id, UpdateFeeStructureRequest $request): FeeStructureResponse
    {
        $before = $this->requireFeeStructure($id);

        $this->feeStructureModel->update($id, ['amount' => $request->amount]);

        $after = $this->feeStructureModel->find($id);

        $this->auditService->record('FeeStructure', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new FeeStructureResponse($after);
    }

    public function getFeeStructure(int $id): FeeStructureResponse
    {
        return new FeeStructureResponse($this->requireFeeStructure($id));
    }

    /**
     * @return list<FeeStructureResponse>
     */
    public function listByClassSessionCategory(int $classId, int $academicSessionId, string $category, ?int $routeId = null): array
    {
        return array_map(
            static fn (FeeStructure $feeStructure): FeeStructureResponse => new FeeStructureResponse($feeStructure),
            $this->feeStructureModel->findByClassSessionCategory($classId, $academicSessionId, $category, $routeId),
        );
    }

    private function requireFeeStructure(int $id): FeeStructure
    {
        $feeStructure = $this->feeStructureModel->find($id);

        if ($feeStructure === null) {
            throw new BusinessRuleException('FEE_STRUCTURE_NOT_FOUND', 'Fee structure not found.');
        }

        return $feeStructure;
    }
}
