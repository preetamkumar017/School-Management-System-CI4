<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

final class UpdateRouteRequest
{
    /**
     * @param list<string> $stopsJson
     */
    public function __construct(
        public readonly array $stopsJson,
        public readonly int $capacity,
        public readonly ?int $vehicleId,
        public readonly ?int $driverId = null,
    ) {
    }
}
