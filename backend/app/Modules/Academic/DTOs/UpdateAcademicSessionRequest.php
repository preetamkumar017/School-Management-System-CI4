<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class UpdateAcademicSessionRequest
{
    public function __construct(
        public readonly string $sessionName,
        public readonly string $startDate,
        public readonly string $endDate,
    ) {
    }
}
