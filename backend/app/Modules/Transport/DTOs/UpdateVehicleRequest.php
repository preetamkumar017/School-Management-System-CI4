<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

final class UpdateVehicleRequest
{
    public function __construct(
        public readonly int $capacity,
        public readonly ?string $gpsDeviceId,
        public readonly ?string $licenseValidUntil,
    ) {
    }
}
