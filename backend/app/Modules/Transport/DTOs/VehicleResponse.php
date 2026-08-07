<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

use App\Modules\Transport\Entities\Vehicle;

/**
 * docs/design/transport/Phase-2-Model-DTO-Design.md
 */
final class VehicleResponse
{
    public readonly int $vehicleId;
    public readonly string $registrationNo;
    public readonly int $capacity;
    public readonly ?string $gpsDeviceId;
    public readonly ?string $licenseValidUntil;

    public function __construct(Vehicle $vehicle)
    {
        $this->vehicleId          = $vehicle->vehicle_id;
        $this->registrationNo     = $vehicle->registration_no;
        $this->capacity           = $vehicle->capacity;
        $this->gpsDeviceId        = $vehicle->gps_device_id;
        $this->licenseValidUntil  = $vehicle->license_valid_until;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vehicle_id'           => $this->vehicleId,
            'registration_no'      => $this->registrationNo,
            'capacity'             => $this->capacity,
            'gps_device_id'        => $this->gpsDeviceId,
            'license_valid_until'  => $this->licenseValidUntil,
        ];
    }
}
