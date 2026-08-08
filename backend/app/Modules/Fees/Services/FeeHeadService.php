<?php

declare(strict_types=1);

namespace App\Modules\Fees\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Fees\DTOs\CreateFeeHeadRequest;
use App\Modules\Fees\DTOs\FeeHeadResponse;
use App\Modules\Fees\DTOs\UpdateFeeHeadRequest;
use App\Modules\Fees\Entities\FeeHead;
use App\Modules\Fees\Models\FeeHeadModel;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * RBAC (ADR-024 §3, Phase 2): `fees.manage` (Tier 1 only) gates writes.
 */
class FeeHeadService
{
    public const PERMISSION_MANAGE = 'fees.manage';

    public function __construct(
        private readonly FeeHeadModel $feeHeadModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createFeeHead(CreateFeeHeadRequest $request): FeeHeadResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->feeHeadModel->existsByName($request->feeHeadName)) {
            throw new BusinessRuleException('FEE_HEAD_NAME_ALREADY_TAKEN', 'This fee head name is already taken.');
        }

        $id = $this->feeHeadModel->insert([
            'fee_head_name' => $request->feeHeadName,
            'is_taxable'    => $request->isTaxable,
            'gst_rate'      => $request->gstRate,
        ], true);

        $feeHead = $this->feeHeadModel->find($id);

        $this->auditService->record('FeeHead', $id, AuditLog::ACTION_CREATE, null, $feeHead->toRawArray());

        return new FeeHeadResponse($feeHead);
    }

    public function updateFeeHead(int $id, UpdateFeeHeadRequest $request): FeeHeadResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireFeeHead($id);

        if ($this->feeHeadModel->existsByNameExceptId($request->feeHeadName, $id)) {
            throw new BusinessRuleException('FEE_HEAD_NAME_ALREADY_TAKEN', 'This fee head name is already taken.');
        }

        $this->feeHeadModel->update($id, [
            'fee_head_name' => $request->feeHeadName,
            'is_taxable'    => $request->isTaxable,
            'gst_rate'      => $request->gstRate,
        ]);

        $after = $this->feeHeadModel->find($id);

        $this->auditService->record('FeeHead', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new FeeHeadResponse($after);
    }

    public function getFeeHead(int $id): FeeHeadResponse
    {
        return new FeeHeadResponse($this->requireFeeHead($id));
    }

    /**
     * @return list<FeeHeadResponse>
     */
    public function listFeeHeads(): array
    {
        return array_map(
            static fn (FeeHead $feeHead): FeeHeadResponse => new FeeHeadResponse($feeHead),
            $this->feeHeadModel->findAll(),
        );
    }

    private function requireFeeHead(int $id): FeeHead
    {
        $feeHead = $this->feeHeadModel->find($id);

        if ($feeHead === null) {
            throw new BusinessRuleException('FEE_HEAD_NOT_FOUND', 'Fee head not found.');
        }

        return $feeHead;
    }
}
