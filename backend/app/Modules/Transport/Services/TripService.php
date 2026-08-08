<?php

declare(strict_types=1);

namespace App\Modules\Transport\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Transport\DTOs\TripResponse;
use App\Modules\Transport\Entities\Driver;
use App\Modules\Transport\Entities\Trip;
use App\Modules\Transport\Entities\Vehicle;
use App\Modules\Transport\Models\DriverModel;
use App\Modules\Transport\Models\RouteModel;
use App\Modules\Transport\Models\TripModel;
use App\Modules\Transport\Models\VehicleModel;
use CodeIgniter\I18n\Time;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md — ADR-019 §5.
 * BR-TRN-006: a trip cannot be logged as started unless both a valid
 * driver and a currently licensed vehicle are assigned to that route.
 * RBAC (ADR-024 §3, Phase 2): `transport.manage` (Tier 1 only) gates
 * `startTrip()` — no per-caller owner on Trip.
 */
class TripService
{
    public const PERMISSION_MANAGE = 'transport.manage';

    public function __construct(
        private readonly TripModel $tripModel,
        private readonly RouteModel $routeModel,
        private readonly DriverModel $driverModel,
        private readonly VehicleModel $vehicleModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    /**
     * The enforcement point for BR-TRN-006. Six distinct, stable error
     * codes (ADR-019 §5) — the BR's own Exception Handling field requires
     * "the specific expired/missing credential identified," not one
     * generic rejection.
     */
    public function startTrip(int $routeId): TripResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $route = $this->routeModel->find($routeId);

        if ($route === null) {
            throw new BusinessRuleException('ROUTE_NOT_FOUND', 'Route not found.');
        }

        if ($route->driver_id === null) {
            throw new BusinessRuleException(
                'DRIVER_NOT_ASSIGNED_TO_ROUTE',
                'No driver is assigned to this route (BR-TRN-006).',
            );
        }

        if ($route->vehicle_id === null) {
            throw new BusinessRuleException(
                'VEHICLE_NOT_ASSIGNED_TO_ROUTE',
                'No vehicle is assigned to this route (BR-TRN-006).',
            );
        }

        // Route::$driver_id/$vehicle_id are typed via BaseEntity's magic
        // __get (Route's casts declare them '?integer'), which PHPStan
        // sees as `mixed` rather than `?int` — the explicit int cast here
        // reflects the Entity's own already-applied runtime cast, letting
        // the CodeIgniter phpstan extension resolve Model::find()'s
        // int-keyed (object|null) overload instead of its array-keyed one.
        $driver  = $this->driverModel->find((int) $route->driver_id);
        $vehicle = $this->vehicleModel->find((int) $route->vehicle_id);

        $this->assertDriverValid($driver);
        $this->assertVehicleValid($vehicle);

        $id = $this->tripModel->insert([
            'route_id'   => $route->route_id,
            'driver_id'  => $driver->driver_id,
            'vehicle_id' => $vehicle->vehicle_id,
            'started_at' => Time::now()->toDateTimeString(),
            'status'     => Trip::STATUS_STARTED,
        ], true);

        $trip = $this->tripModel->find($id);

        $this->auditService->record('Trip', $id, AuditLog::ACTION_CREATE, null, $trip->toRawArray());

        return new TripResponse($trip);
    }

    public function getTrip(int $id): TripResponse
    {
        $trip = $this->tripModel->find($id);

        if ($trip === null) {
            throw new BusinessRuleException('TRIP_NOT_FOUND', 'Trip not found.');
        }

        return new TripResponse($trip);
    }

    /**
     * @return list<TripResponse>
     */
    public function listByRoute(int $routeId): array
    {
        return array_map(
            static fn (Trip $trip): TripResponse => new TripResponse($trip),
            $this->tripModel->findByRouteId($routeId),
        );
    }

    private function assertDriverValid(?Driver $driver): void
    {
        if ($driver === null) {
            throw new BusinessRuleException('DRIVER_NOT_FOUND', 'Driver not found.');
        }

        if ($driver->status !== Driver::STATUS_ACTIVE) {
            throw new BusinessRuleException(
                'DRIVER_INACTIVE',
                'The driver assigned to this route is not active (BR-TRN-006).',
            );
        }

        if ($driver->license_valid_until === null) {
            throw new BusinessRuleException(
                'DRIVER_LICENSE_MISSING',
                'The driver assigned to this route has no license validity on file (BR-TRN-006).',
            );
        }

        if ($driver->license_valid_until < $this->today()) {
            throw new BusinessRuleException(
                'DRIVER_LICENSE_EXPIRED',
                'The driver assigned to this route has an expired license (BR-TRN-006).',
            );
        }
    }

    private function assertVehicleValid(?Vehicle $vehicle): void
    {
        if ($vehicle === null) {
            throw new BusinessRuleException('VEHICLE_NOT_FOUND', 'Vehicle not found.');
        }

        if ($vehicle->license_valid_until === null) {
            throw new BusinessRuleException(
                'VEHICLE_LICENSE_MISSING',
                'The vehicle assigned to this route has no license validity on file (BR-TRN-006).',
            );
        }

        if ($vehicle->license_valid_until < $this->today()) {
            throw new BusinessRuleException(
                'VEHICLE_LICENSE_EXPIRED',
                'The vehicle assigned to this route has an expired license (BR-TRN-006).',
            );
        }
    }

    private function today(): string
    {
        return Time::now()->toDateString();
    }
}
