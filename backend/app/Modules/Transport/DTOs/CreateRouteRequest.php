<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

final class CreateRouteRequest
{
    /**
     * @param list<string> $stopsJson
     */
    public function __construct(
        public readonly string $routeName,
        public readonly array $stopsJson,
        public readonly int $capacity,
        public readonly ?int $vehicleId,
        public readonly ?int $driverId = null,
    ) {
    }
}
