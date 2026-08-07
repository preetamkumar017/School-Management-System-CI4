<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

use App\Modules\Transport\Entities\Driver;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md
 */
final class DriverResponse
{
    public readonly int $driverId;
    public readonly string $fullName;
    public readonly string $licenseNumber;
    public readonly ?string $licenseValidUntil;
    public readonly string $status;

    public function __construct(Driver $driver)
    {
        $this->driverId           = $driver->driver_id;
        $this->fullName           = $driver->full_name;
        $this->licenseNumber      = $driver->license_number;
        $this->licenseValidUntil  = $driver->license_valid_until;
        $this->status             = $driver->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver_id'            => $this->driverId,
            'full_name'            => $this->fullName,
            'license_number'       => $this->licenseNumber,
            'license_valid_until'  => $this->licenseValidUntil,
            'status'               => $this->status,
        ];
    }
}
