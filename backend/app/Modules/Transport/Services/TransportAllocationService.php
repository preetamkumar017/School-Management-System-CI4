<?php

declare(strict_types=1);

namespace App\Modules\Transport\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Transport\DTOs\AllocateTransportRequest;
use App\Modules\Transport\DTOs\ChangeRouteRequest;
use App\Modules\Transport\DTOs\TransportAllocationResponse;
use App\Modules\Transport\Entities\TransportAllocation;
use App\Modules\Transport\Models\RouteModel;
use App\Modules\Transport\Models\TransportAllocationModel;
use Config\Database;
use Config\Services as AppServices;

/**
 * docs/design/transport/Phase-3-Service-Controller-Design.md
 */
class TransportAllocationService
{
    public function __construct(
        private readonly TransportAllocationModel $transportAllocationModel,
        private readonly RouteModel $routeModel,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * BR-TRN-001/002 (ADR-009 §9/§10): a genuine row lock on the Route,
     * the same concurrency-safe shape
     * `SeatAllocationModel::incrementSeatsFilled` established in Stage 4
     * for Admission.
     */
    public function allocate(AllocateTransportRequest $request): TransportAllocationResponse
    {
        AppServices::studentService()->getStudent($request->studentId);

        if (! preg_match('/^\d{10}$/', $request->emergencyContact)) {
            throw new ValidationException(['emergency_contact' => 'emergency_contact must be a 10-digit numeric value (BR-TRN-004).']);
        }

        $db = Database::connect();
        $db->transStart();

        $route = $this->routeModel->findForUpdate($request->routeId);

        if ($route === null) {
            $db->transComplete();

            throw new BusinessRuleException('ROUTE_NOT_FOUND', 'Route not found.');
        }

        if (! in_array($request->stopName, $route->stops_json, true)) {
            $db->transComplete();

            throw new ValidationException(['stop_name' => 'stop_name must be one of the route\'s configured stops.']);
        }

        if ($this->transportAllocationModel->existsActiveByStudentId($request->studentId)) {
            $db->transComplete();

            throw new BusinessRuleException(
                'STUDENT_ALREADY_ALLOCATED',
                'This student already has an active transport allocation (BR-TRN-002).',
            );
        }

        if ($this->transportAllocationModel->countActiveByRouteId($request->routeId) >= $route->capacity) {
            $db->transComplete();

            throw new BusinessRuleException(
                'ROUTE_CAPACITY_EXCEEDED',
                'This route is already at its configured capacity (BR-TRN-001).',
            );
        }

        $id = $this->transportAllocationModel->insert([
            'student_id'        => $request->studentId,
            'route_id'          => $request->routeId,
            'stop_name'         => $request->stopName,
            'emergency_contact' => $request->emergencyContact,
            'status'            => TransportAllocation::STATUS_ACTIVE,
        ], true);

        $allocation = $this->transportAllocationModel->find($id);

        $this->auditService->record('TransportAllocation', $id, AuditLog::ACTION_CREATE, null, $allocation->toRawArray());

        $db->transComplete();

        return new TransportAllocationResponse($allocation);
    }

    public function deallocate(int $id): TransportAllocationResponse
    {
        $before = $this->requireAllocation($id);

        if ($before->status !== TransportAllocation::STATUS_ACTIVE) {
            throw new BusinessRuleException(
                'TRANSPORT_ALLOCATION_INVALID_STATUS_TRANSITION',
                "Cannot de-allocate a transport allocation in status {$before->status}.",
            );
        }

        $this->transportAllocationModel->update($id, ['status' => TransportAllocation::STATUS_DEALLOCATED]);
        $after = $this->transportAllocationModel->find($id);

        $this->auditService->record('TransportAllocation', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new TransportAllocationResponse($after);
    }

    public function getAllocation(int $id): TransportAllocationResponse
    {
        return new TransportAllocationResponse($this->requireAllocation($id));
    }

    /**
     * ADR-014 §1 — read-only lookup Fees' InvoiceService calls to fold a
     * route-tier fee into invoice generation. Null when the student has
     * no active allocation.
     */
    public function getActiveAllocationForStudent(int $studentId): ?TransportAllocationResponse
    {
        $allocation = $this->transportAllocationModel->findActiveByStudentId($studentId);

        return $allocation === null ? null : new TransportAllocationResponse($allocation);
    }

    /**
     * BR-TRN-005 (ADR-014 §1): moves an active allocation to a new route
     * (same capacity/stop checks as allocate()) and then pushes the fact
     * into Fees via the explicit InvoiceService::recalculateForRouteChange
     * trigger — Transport initiates because Transport is the module that
     * knows the event happened; Fees never polls Transport for this.
     */
    public function changeRoute(int $id, ChangeRouteRequest $request): TransportAllocationResponse
    {
        $before = $this->requireAllocation($id);

        if ($before->status !== TransportAllocation::STATUS_ACTIVE) {
            throw new BusinessRuleException(
                'TRANSPORT_ALLOCATION_INVALID_STATUS_TRANSITION',
                "Cannot change the route of a transport allocation in status {$before->status}.",
            );
        }

        $db = Database::connect();
        $db->transStart();

        $route = $this->routeModel->findForUpdate($request->routeId);

        if ($route === null) {
            $db->transComplete();

            throw new BusinessRuleException('ROUTE_NOT_FOUND', 'Route not found.');
        }

        if (! in_array($request->stopName, $route->stops_json, true)) {
            $db->transComplete();

            throw new ValidationException(['stop_name' => 'stop_name must be one of the route\'s configured stops.']);
        }

        if ($request->routeId !== $before->route_id && $this->transportAllocationModel->countActiveByRouteId($request->routeId) >= $route->capacity) {
            $db->transComplete();

            throw new BusinessRuleException(
                'ROUTE_CAPACITY_EXCEEDED',
                'This route is already at its configured capacity (BR-TRN-001).',
            );
        }

        $this->transportAllocationModel->update($id, [
            'route_id'  => $request->routeId,
            'stop_name' => $request->stopName,
        ]);

        $after = $this->transportAllocationModel->find($id);

        $this->auditService->record('TransportAllocation', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        $db->transComplete();

        AppServices::invoiceService()->recalculateForRouteChange($after->student_id, $after->route_id);

        return new TransportAllocationResponse($after);
    }

    /**
     * @return list<TransportAllocationResponse>
     */
    public function listByRoute(int $routeId): array
    {
        return array_map(
            static fn (TransportAllocation $allocation): TransportAllocationResponse => new TransportAllocationResponse($allocation),
            $this->transportAllocationModel->findByRouteId($routeId),
        );
    }

    private function requireAllocation(int $id): TransportAllocation
    {
        $allocation = $this->transportAllocationModel->find($id);

        if ($allocation === null) {
            throw new BusinessRuleException('TRANSPORT_ALLOCATION_NOT_FOUND', 'Transport allocation not found.');
        }

        return $allocation;
    }
}
