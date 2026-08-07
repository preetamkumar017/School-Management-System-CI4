<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

use App\Modules\Transport\Entities\Trip;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md
 */
final class TripResponse
{
    public readonly int $tripId;
    public readonly int $routeId;
    public readonly int $driverId;
    public readonly int $vehicleId;
    public readonly string $startedAt;
    public readonly string $status;

    public function __construct(Trip $trip)
    {
        $this->tripId    = $trip->trip_id;
        $this->routeId   = $trip->route_id;
        $this->driverId  = $trip->driver_id;
        $this->vehicleId = $trip->vehicle_id;
        $this->startedAt = $trip->started_at;
        $this->status    = $trip->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trip_id'    => $this->tripId,
            'route_id'   => $this->routeId,
            'driver_id'  => $this->driverId,
            'vehicle_id' => $this->vehicleId,
            'started_at' => $this->startedAt,
            'status'     => $this->status,
        ];
    }
}
