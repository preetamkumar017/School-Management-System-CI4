<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

final class CreateVehicleRequest
{
    public function __construct(
        public readonly string $registrationNo,
        public readonly int $capacity,
        public readonly ?string $gpsDeviceId,
        public readonly ?string $licenseValidUntil,
    ) {
    }
}
