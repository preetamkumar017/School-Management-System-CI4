<?php

declare(strict_types=1);

namespace App\Modules\Admission\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Admission\DTOs\CreateSeatAllocationRequest;
use App\Modules\Admission\DTOs\SeatAllocationResponse;
use App\Modules\Admission\DTOs\UpdateSeatAllocationCapacityRequest;
use App\Modules\Admission\Entities\SeatAllocation;
use App\Modules\Admission\Models\SeatAllocationModel;
use Config\Services as AppServices;

/**
 * docs/design/admission/Phase-4-Service-Design.md
 * Core CRUD only. incrementSeatsFilled's pessimistic-locking counter
 * update lives on SeatAllocationModel directly (Phase 4's decision) and
 * is invoked by Stage 5's Confirm Enrollment orchestration, not exposed
 * as a public method here.
 */
class SeatAllocationService
{
    public function __construct(
        private readonly SeatAllocationModel $seatAllocationModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createSeatAllocation(CreateSeatAllocationRequest $request): SeatAllocationResponse
    {
        AppServices::classService()->getClass($request->classId);
        AppServices::academicSessionService()->getSession($request->academicSessionId);

        if ($this->seatAllocationModel->existsByClassAndSession($request->classId, $request->academicSessionId)) {
            throw new BusinessRuleException(
                'SEAT_ALLOCATION_ALREADY_EXISTS',
                'A seat allocation already exists for this class and academic session.',
            );
        }

        $this->assertRteWithinCeiling($request->totalCapacity, $request->rteQuotaCapacity);

        $id = $this->seatAllocationModel->insert([
            'class_id'            => $request->classId,
            'academic_session_id' => $request->academicSessionId,
            'total_capacity'      => $request->totalCapacity,
            'rte_quota_capacity'  => $request->rteQuotaCapacity,
        ], true);

        $seatAllocation = $this->seatAllocationModel->find($id);

        $this->auditService->record(
            'SeatAllocation',
            $id,
            AuditLog::ACTION_CREATE,
            null,
            $seatAllocation->toRawArray(),
        );

        return new SeatAllocationResponse($seatAllocation);
    }

    public function updateCapacity(int $id, UpdateSeatAllocationCapacityRequest $request): SeatAllocationResponse
    {
        $before = $this->requireSeatAllocation($id);

        if ($request->totalCapacity < $before->seats_filled) {
            throw new BusinessRuleException(
                'SEAT_ALLOCATION_CAPACITY_BELOW_SEATS_FILLED',
                'total_capacity cannot be reduced below the number of seats already filled.',
            );
        }

        $this->assertRteWithinCeiling($request->totalCapacity, $request->rteQuotaCapacity);

        $this->seatAllocationModel->update($id, [
            'total_capacity'     => $request->totalCapacity,
            'rte_quota_capacity' => $request->rteQuotaCapacity,
        ]);

        $after = $this->seatAllocationModel->find($id);

        $this->auditService->record(
            'SeatAllocation',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new SeatAllocationResponse($after);
    }

    public function getSeatAllocation(int $id): SeatAllocationResponse
    {
        return new SeatAllocationResponse($this->requireSeatAllocation($id));
    }

    public function findForClassAndSession(int $classId, int $academicSessionId): ?SeatAllocationResponse
    {
        $seatAllocation = $this->seatAllocationModel->findByClassAndSession($classId, $academicSessionId);

        return $seatAllocation === null ? null : new SeatAllocationResponse($seatAllocation);
    }

    /**
     * docs/ADR/ADR-022-reports-dashboard.md — Admissions funnel (report
     * area 3): every SeatAllocation for a session, for seat-occupancy
     * reporting and to resolve "this session's classes" for
     * ApplicationService::getStatusCountsForClassIds(). Reports composes
     * this instead of touching SeatAllocationModel directly (ADR-010 §7).
     *
     * @return list<SeatAllocationResponse>
     */
    public function listForAcademicSession(int $academicSessionId): array
    {
        return array_map(
            static fn (SeatAllocation $seatAllocation): SeatAllocationResponse => new SeatAllocationResponse($seatAllocation),
            $this->seatAllocationModel->findByAcademicSessionId($academicSessionId),
        );
    }

    private function requireSeatAllocation(int $id): SeatAllocation
    {
        $seatAllocation = $this->seatAllocationModel->find($id);

        if ($seatAllocation === null) {
            throw new BusinessRuleException('SEAT_ALLOCATION_NOT_FOUND', 'Seat allocation not found.');
        }

        return $seatAllocation;
    }

    private function assertRteWithinCeiling(int $totalCapacity, int $rteQuotaCapacity): void
    {
        if ($rteQuotaCapacity > (int) floor($totalCapacity * 0.25)) {
            throw new BusinessRuleException(
                'SEAT_ALLOCATION_RTE_QUOTA_EXCEEDS_CEILING',
                'rte_quota_capacity cannot exceed 25% of total_capacity.',
            );
        }
    }
}
