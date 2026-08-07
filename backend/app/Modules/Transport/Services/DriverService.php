<?php

declare(strict_types=1);

namespace App\Modules\Transport\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Transport\DTOs\CreateDriverRequest;
use App\Modules\Transport\DTOs\DriverResponse;
use App\Modules\Transport\DTOs\UpdateDriverRequest;
use App\Modules\Transport\Entities\Driver;
use App\Modules\Transport\Models\DriverModel;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md — ADR-019 §1.
 * Transport-Coordinator-maintained, same CRUD shape as VehicleService.
 */
class DriverService
{
    public function __construct(
        private readonly DriverModel $driverModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createDriver(CreateDriverRequest $request): DriverResponse
    {
        if ($this->driverModel->existsByLicenseNumber($request->licenseNumber)) {
            throw new BusinessRuleException('DRIVER_LICENSE_NUMBER_ALREADY_EXISTS', 'A driver with this license number already exists.');
        }

        $id = $this->driverModel->insert([
            'full_name'           => $request->fullName,
            'license_number'      => $request->licenseNumber,
            'license_valid_until' => $request->licenseValidUntil,
            'status'              => $request->status,
        ], true);

        $driver = $this->driverModel->find($id);

        $this->auditService->record('Driver', $id, AuditLog::ACTION_CREATE, null, $driver->toRawArray());

        return new DriverResponse($driver);
    }

    public function updateDriver(int $id, UpdateDriverRequest $request): DriverResponse
    {
        $before = $this->requireDriver($id);

        $this->driverModel->update($id, [
            'full_name'           => $request->fullName,
            'license_valid_until' => $request->licenseValidUntil,
            'status'              => $request->status,
        ]);

        $after = $this->driverModel->find($id);

        $this->auditService->record('Driver', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new DriverResponse($after);
    }

    public function getDriver(int $id): DriverResponse
    {
        return new DriverResponse($this->requireDriver($id));
    }

    /**
     * @return list<DriverResponse>
     */
    public function listDrivers(): array
    {
        return array_map(
            static fn (Driver $driver): DriverResponse => new DriverResponse($driver),
            $this->driverModel->findAll(),
        );
    }

    private function requireDriver(int $id): Driver
    {
        $driver = $this->driverModel->find($id);

        if ($driver === null) {
            throw new BusinessRuleException('DRIVER_NOT_FOUND', 'Driver not found.');
        }

        return $driver;
    }
}
