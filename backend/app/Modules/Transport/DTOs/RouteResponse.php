<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

use App\Modules\Transport\Entities\Route;

/**
 * docs/design/transport/Phase-2-Model-DTO-Design.md
 */
final class RouteResponse
{
    public readonly int $routeId;
    public readonly string $routeName;

    /** @var list<string> */
    public readonly array $stopsJson;
    public readonly int $capacity;
    public readonly ?int $vehicleId;
    public readonly ?int $driverId;

    public function __construct(Route $route)
    {
        $this->routeId   = $route->route_id;
        $this->routeName = $route->route_name;
        $this->stopsJson = $route->stops_json;
        $this->capacity  = $route->capacity;
        $this->vehicleId = $route->vehicle_id;
        $this->driverId  = $route->driver_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'route_id'   => $this->routeId,
            'route_name' => $this->routeName,
            'stops_json' => $this->stopsJson,
            'capacity'   => $this->capacity,
            'vehicle_id' => $this->vehicleId,
            'driver_id'  => $this->driverId,
        ];
    }
}
