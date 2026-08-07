<?php

declare(strict_types=1);

namespace App\Modules\Transport\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Transport\DTOs\CreateRouteRequest;
use App\Modules\Transport\DTOs\RouteResponse;
use App\Modules\Transport\DTOs\UpdateRouteRequest;
use App\Modules\Transport\Entities\Route;
use App\Modules\Transport\Models\RouteModel;
use App\Modules\Transport\Models\VehicleModel;

/**
 * docs/design/transport/Phase-3-Service-Controller-Design.md
 */
class RouteService
{
    public function __construct(
        private readonly RouteModel $routeModel,
        private readonly VehicleModel $vehicleModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createRoute(CreateRouteRequest $request): RouteResponse
    {
        if ($this->routeModel->existsByName($request->routeName)) {
            throw new BusinessRuleException('ROUTE_NAME_ALREADY_EXISTS', 'A route with this name already exists.');
        }

        $this->assertCapacityWithinVehicle($request->capacity, $request->vehicleId);

        $id = $this->routeModel->insert([
            'route_name' => $request->routeName,
            'stops_json' => $request->stopsJson,
            'capacity'   => $request->capacity,
            'vehicle_id' => $request->vehicleId,
        ], true);

        $route = $this->routeModel->find($id);

        $this->auditService->record('Route', $id, AuditLog::ACTION_CREATE, null, $route->toRawArray());

        return new RouteResponse($route);
    }

    public function updateRoute(int $id, UpdateRouteRequest $request): RouteResponse
    {
        $before = $this->requireRoute($id);

        $this->assertCapacityWithinVehicle($request->capacity, $request->vehicleId);

        $this->routeModel->update($id, [
            'stops_json' => $request->stopsJson,
            'capacity'   => $request->capacity,
            'vehicle_id' => $request->vehicleId,
        ]);

        $after = $this->routeModel->find($id);

        $this->auditService->record('Route', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new RouteResponse($after);
    }

    public function getRoute(int $id): RouteResponse
    {
        return new RouteResponse($this->requireRoute($id));
    }

    /**
     * @return list<RouteResponse>
     */
    public function listRoutes(): array
    {
        return array_map(
            static fn (Route $route): RouteResponse => new RouteResponse($route),
            $this->routeModel->findAll(),
        );
    }

    /**
     * BR-TRN-001 (ADR-009 §9): Route.capacity must not exceed the
     * assigned Vehicle's capacity, when a vehicle is assigned.
     */
    private function assertCapacityWithinVehicle(int $capacity, ?int $vehicleId): void
    {
        if ($vehicleId === null) {
            return;
        }

        $vehicle = $this->vehicleModel->find($vehicleId);

        if ($vehicle === null) {
            throw new BusinessRuleException('VEHICLE_NOT_FOUND', 'Vehicle not found.');
        }

        if ($capacity > $vehicle->capacity) {
            throw new BusinessRuleException(
                'ROUTE_CAPACITY_EXCEEDS_VEHICLE',
                'Route capacity cannot exceed the assigned vehicle\'s seating capacity (BR-TRN-001).',
            );
        }
    }

    private function requireRoute(int $id): Route
    {
        $route = $this->routeModel->find($id);

        if ($route === null) {
            throw new BusinessRuleException('ROUTE_NOT_FOUND', 'Route not found.');
        }

        return $route;
    }
}
