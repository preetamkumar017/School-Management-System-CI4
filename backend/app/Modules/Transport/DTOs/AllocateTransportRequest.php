<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

final class AllocateTransportRequest
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $routeId,
        public readonly string $stopName,
        public readonly string $emergencyContact,
    ) {
    }
}
