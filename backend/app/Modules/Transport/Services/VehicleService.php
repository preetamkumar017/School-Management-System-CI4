<?php

declare(strict_types=1);

namespace App\Modules\Transport\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Transport\DTOs\CreateVehicleRequest;
use App\Modules\Transport\DTOs\UpdateVehicleRequest;
use App\Modules\Transport\DTOs\VehicleResponse;
use App\Modules\Transport\Entities\Vehicle;
use App\Modules\Transport\Models\VehicleModel;

/**
 * docs/design/transport/Phase-3-Service-Controller-Design.md
 * RBAC (ADR-024 §3, Phase 2): `transport.manage` (Tier 1 only) gates writes.
 */
class VehicleService
{
    public const PERMISSION_MANAGE = 'transport.manage';

    public function __construct(
        private readonly VehicleModel $vehicleModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createVehicle(CreateVehicleRequest $request): VehicleResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->vehicleModel->existsByRegistrationNo($request->registrationNo)) {
            throw new BusinessRuleException('VEHICLE_REGISTRATION_ALREADY_EXISTS', 'A vehicle with this registration number already exists.');
        }

        $id = $this->vehicleModel->insert([
            'registration_no'     => $request->registrationNo,
            'capacity'            => $request->capacity,
            'gps_device_id'       => $request->gpsDeviceId,
            'license_valid_until' => $request->licenseValidUntil,
        ], true);

        $vehicle = $this->vehicleModel->find($id);

        $this->auditService->record('Vehicle', $id, AuditLog::ACTION_CREATE, null, $vehicle->toRawArray());

        return new VehicleResponse($vehicle);
    }

    public function updateVehicle(int $id, UpdateVehicleRequest $request): VehicleResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireVehicle($id);

        $this->vehicleModel->update($id, [
            'capacity'            => $request->capacity,
            'gps_device_id'       => $request->gpsDeviceId,
            'license_valid_until' => $request->licenseValidUntil,
        ]);

        $after = $this->vehicleModel->find($id);

        $this->auditService->record('Vehicle', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new VehicleResponse($after);
    }

    public function getVehicle(int $id): VehicleResponse
    {
        return new VehicleResponse($this->requireVehicle($id));
    }

    /**
     * @return list<VehicleResponse>
     */
    public function listVehicles(): array
    {
        return array_map(
            static fn (Vehicle $vehicle): VehicleResponse => new VehicleResponse($vehicle),
            $this->vehicleModel->findAll(),
        );
    }

    private function requireVehicle(int $id): Vehicle
    {
        $vehicle = $this->vehicleModel->find($id);

        if ($vehicle === null) {
            throw new BusinessRuleException('VEHICLE_NOT_FOUND', 'Vehicle not found.');
        }

        return $vehicle;
    }
}
