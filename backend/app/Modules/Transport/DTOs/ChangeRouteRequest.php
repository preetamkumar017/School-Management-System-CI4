<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

final class ChangeRouteRequest
{
    public function __construct(
        public readonly int $routeId,
        public readonly string $stopName,
    ) {
    }
}
